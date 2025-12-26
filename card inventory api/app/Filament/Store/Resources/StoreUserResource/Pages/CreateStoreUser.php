<?php

namespace App\Filament\Store\Resources\StoreUserResource\Pages;

use App\Filament\Store\Resources\StoreUserResource;
use App\Models\StoreUserRole;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateStoreUser extends CreateRecord
{
    protected static string $resource = StoreUserResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Remove role from user data (we'll handle it separately)
        unset($data['role']);

        return $data;
    }

    protected function afterCreate(): void
    {
        // Assign role to the newly created user
        $currentStore = Auth::user()->currentStore();
        $role = $this->form->getState()['role'] ?? 'base';

        if ($currentStore) {
            StoreUserRole::create([
                'user_id' => $this->record->id,
                'store_id' => $currentStore->id,
                'role' => $role,
            ]);
        }
    }

    protected function authorizeAccess(): void
    {
        $currentStore = Auth::user()->currentStore();
        if (!$currentStore) {
            abort(403, 'No store selected');
        }

        // Only owners can add users
        abort_unless(
            Auth::user()->hasRole($currentStore, 'owner'),
            403,
            'Only store owners can add users to the store.'
        );
    }
}

