<?php

namespace App\Policies;

use App\Models\Store;
use App\Models\User;

class StorePolicy
{
    /**
     * Determine if the user can view any stores.
     */
    public function viewAny(User $user): bool
    {
        // Platform admins can view all stores
        if ($user->isPlatformAdmin()) {
            return true;
        }

        // Store users can view stores they belong to
        return $user->stores()->exists();
    }

    /**
     * Determine if the user can view the store.
     */
    public function view(User $user, Store $store): bool
    {
        // Platform admins can view any store
        if ($user->isPlatformAdmin()) {
            return true;
        }

        // Store users can only view their own stores
        return $user->canAccessStore($store);
    }

    /**
     * Determine if the user can create stores.
     */
    public function create(User $user): bool
    {
        // Only platform admins can create stores via admin panel
        // Store owners create stores via registration flow
        return $user->isPlatformAdmin();
    }

    /**
     * Determine if the user can update the store.
     */
    public function update(User $user, Store $store): bool
    {
        // Platform admins can update any store
        if ($user->isPlatformAdmin()) {
            return true;
        }

        // Only store owners can update their store
        return $user->hasRole($store, 'owner');
    }

    /**
     * Determine if the user can delete the store.
     */
    public function delete(User $user, Store $store): bool
    {
        // Platform admins can delete any store
        if ($user->isPlatformAdmin()) {
            return true;
        }

        // Only store owners can delete their store
        return $user->hasRole($store, 'owner');
    }

    /**
     * Determine if the user can manage users for the store.
     */
    public function manageUsers(User $user, Store $store): bool
    {
        // Platform admins can manage users for any store
        if ($user->isPlatformAdmin()) {
            return true;
        }

        // Only store owners can manage users
        return $user->hasRole($store, 'owner');
    }

    /**
     * Determine if the user can manage locations for the store.
     */
    public function manageLocations(User $user, Store $store): bool
    {
        // Platform admins can manage locations for any store
        if ($user->isPlatformAdmin()) {
            return true;
        }

        // Owners and admins can manage locations
        return $user->hasRole($store, 'owner') || $user->hasRole($store, 'admin');
    }

    /**
     * Determine if the user can manage marketplace integrations for the store.
     */
    public function manageIntegrations(User $user, Store $store): bool
    {
        // Platform admins can manage integrations for any store
        if ($user->isPlatformAdmin()) {
            return true;
        }

        // Owners and admins can manage integrations
        return $user->hasRole($store, 'owner') || $user->hasRole($store, 'admin');
    }
}

