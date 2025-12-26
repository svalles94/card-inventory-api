<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class MakePlatformAdmin extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'user:make-platform-admin 
                            {email : The email of the user to make a platform admin}
                            {--all : Make all existing users platform admins}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Mark a user as a platform admin';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if ($this->option('all')) {
            return $this->makeAllPlatformAdmins();
        }

        $email = $this->argument('email');
        $user = User::where('email', $email)->first();

        if (!$user) {
            $this->error("User with email '{$email}' not found.");
            return Command::FAILURE;
        }

        if ($user->is_platform_admin) {
            $this->warn("User '{$email}' is already a platform admin.");
            return Command::SUCCESS;
        }

        $user->update(['is_platform_admin' => true]);
        $this->info("✅ User '{$email}' is now a platform admin.");

        return Command::SUCCESS;
    }

    /**
     * Make all existing users platform admins
     */
    protected function makeAllPlatformAdmins(): int
    {
        $count = User::where('is_platform_admin', false)->update(['is_platform_admin' => true]);
        $this->info("✅ Made {$count} user(s) platform admins.");
        return Command::SUCCESS;
    }
}

