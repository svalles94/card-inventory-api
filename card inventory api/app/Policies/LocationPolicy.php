<?php

namespace App\Policies;

use App\Models\Location;
use App\Models\User;

class LocationPolicy
{
    /**
     * Determine if the user can view any locations.
     */
    public function viewAny(User $user): bool
    {
        // Platform admins can view all locations
        if ($user->isPlatformAdmin()) {
            return true;
        }

        // Store users can view locations for their stores
        return $user->stores()->exists();
    }

    /**
     * Determine if the user can view the location.
     */
    public function view(User $user, Location $location): bool
    {
        // Platform admins can view any location
        if ($user->isPlatformAdmin()) {
            return true;
        }

        // Check if user has access to the store
        return $user->canAccessStore($location->store);
    }

    /**
     * Determine if the user can create locations.
     */
    public function create(User $user): bool
    {
        // Platform admins can create locations
        if ($user->isPlatformAdmin()) {
            return true;
        }

        // Store users can create locations if they have access to at least one store
        return $user->stores()->exists();
    }

    /**
     * Determine if the user can update the location.
     */
    public function update(User $user, Location $location): bool
    {
        // Platform admins can update any location
        if ($user->isPlatformAdmin()) {
            return true;
        }

        // Check if user has access to the store
        if (!$user->canAccessStore($location->store)) {
            return false;
        }

        // Owners and admins can update locations
        return $user->hasRole($location->store, 'owner') || $user->hasRole($location->store, 'admin');
    }

    /**
     * Determine if the user can delete the location.
     */
    public function delete(User $user, Location $location): bool
    {
        // Platform admins can delete any location
        if ($user->isPlatformAdmin()) {
            return true;
        }

        // Check if user has access to the store
        if (!$user->canAccessStore($location->store)) {
            return false;
        }

        // Only owners and admins can delete locations
        return $user->hasRole($location->store, 'owner') || $user->hasRole($location->store, 'admin');
    }
}

