<?php

namespace App\Console\Commands;

use App\Models\Store;
use App\Models\StoreUserRole;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class CreateStoreUser extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'store:create-user 
                            {--email= : Email for the new user}
                            {--name= : Name for the new user}
                            {--password= : Password (defaults to "password")}
                            {--store-name= : Name of the store to create}
                            {--existing-user= : Use existing user by email}
                            {--existing-store= : Use existing store by ID}
                            {--role=owner : Role to assign (owner/admin/base)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a store user and optionally create a store';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $user = null;
        $store = null;

        // Handle existing user
        if ($existingUserEmail = $this->option('existing-user')) {
            $user = User::where('email', $existingUserEmail)->first();
            if (!$user) {
                $this->error("User with email '{$existingUserEmail}' not found.");
                return Command::FAILURE;
            }
            $this->info("Using existing user: {$user->email}");
        } else {
            // Create new user
            $email = $this->option('email') ?? $this->ask('Email address');
            $name = $this->option('name') ?? $this->ask('User name');
            $password = $this->option('password') ?? 'password';

            if (User::where('email', $email)->exists()) {
                $this->error("User with email '{$email}' already exists.");
                if (!$this->confirm('Use existing user instead?')) {
                    return Command::FAILURE;
                }
                $user = User::where('email', $email)->first();
            } else {
                $user = User::create([
                    'name' => $name,
                    'email' => $email,
                    'password' => Hash::make($password),
                    'is_platform_admin' => false,
                ]);
                $this->info("✅ Created user: {$user->email}");
            }
        }

        // Handle existing store
        if ($existingStoreId = $this->option('existing-store')) {
            $store = Store::find($existingStoreId);
            if (!$store) {
                $this->error("Store with ID '{$existingStoreId}' not found.");
                return Command::FAILURE;
            }
            $this->info("Using existing store: {$store->name}");
        } else {
            // Create new store
            $storeName = $this->option('store-name') ?? $this->ask('Store name', $user->name . "'s Store");

            $store = Store::create([
                'owner_id' => $user->id,
                'name' => $storeName,
                'email' => $user->email,
            ]);
            $this->info("✅ Created store: {$store->name}");
        }

        // Assign role
        $role = $this->option('role');
        if (!in_array($role, ['owner', 'admin', 'base'])) {
            $this->error("Invalid role. Must be: owner, admin, or base");
            return Command::FAILURE;
        }

        // Check if role already exists
        $existingRole = StoreUserRole::where('user_id', $user->id)
            ->where('store_id', $store->id)
            ->where('role', $role)
            ->first();

        if ($existingRole) {
            $this->warn("User already has '{$role}' role in this store.");
        } else {
            StoreUserRole::create([
                'user_id' => $user->id,
                'store_id' => $store->id,
                'role' => $role,
            ]);
            $this->info("✅ Assigned '{$role}' role to {$user->email} in {$store->name}");
        }

        // Summary
        $this->newLine();
        $this->info('📊 Summary:');
        $this->table(
            ['Item', 'Value'],
            [
                ['User', "{$user->name} ({$user->email})"],
                ['Store', "{$store->name} (ID: {$store->id})"],
                ['Role', $role],
                ['Store Panel URL', url('/store')],
            ]
        );

        $this->newLine();
        $this->info('🔑 Login credentials:');
        $this->line("   Email: {$user->email}");
        $this->line("   Password: " . ($this->option('password') ?? 'password'));

        return Command::SUCCESS;
    }
}

