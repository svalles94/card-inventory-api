<?php

namespace App\Policies;

use App\Models\Inventory;
use App\Models\User;

class InventoryPolicy
{
    /**
     * Determine if the user can view any inventory.
     */
    public function viewAny(User $user): bool
    {
        // Platform admins can view all inventory
        if ($user->isPlatformAdmin()) {
            return true;
        }

        // Store users can view inventory for their stores
        return $user->stores()->exists();
    }

    /**
     * Determine if the user can view the inventory.
     */
    public function view(User $user, Inventory $inventory): bool
    {
        // Platform admins can view any inventory
        if ($user->isPlatformAdmin()) {
            return true;
        }

        // Check if user has access to the store that owns this inventory's location
        $store = $inventory->location->store;
        return $user->canAccessStore($store);
    }

    /**
     * Determine if the user can create inventory.
     */
    public function create(User $user): bool
    {
        // Platform admins can create inventory
        if ($user->isPlatformAdmin()) {
            return true;
        }

        // Store users can create inventory if they have access to at least one store
        return $user->stores()->exists();
    }

    /**
     * Determine if the user can update the inventory.
     */
    public function update(User $user, Inventory $inventory): bool
    {
        // Platform admins can update any inventory
        if ($user->isPlatformAdmin()) {
            return true;
        }

        // Check if user has access to the store
        $store = $inventory->location->store;
        if (!$user->canAccessStore($store)) {
            return false;
        }

        // Owners and admins can update inventory
        // Base users can only adjust quantities (handled in adjustQuantity method)
        return $user->hasRole($store, 'owner') || $user->hasRole($store, 'admin');
    }

    /**
     * Determine if the user can delete the inventory.
     */
    public function delete(User $user, Inventory $inventory): bool
    {
        // Platform admins can delete any inventory
        if ($user->isPlatformAdmin()) {
            return true;
        }

        // Check if user has access to the store
        $store = $inventory->location->store;
        if (!$user->canAccessStore($store)) {
            return false;
        }

        // Only owners and admins can delete inventory
        return $user->hasRole($store, 'owner') || $user->hasRole($store, 'admin');
    }

    /**
     * Determine if the user can adjust inventory quantity.
     * Base users can adjust but not set directly.
     */
    public function adjustQuantity(User $user, Inventory $inventory): bool
    {
        // Platform admins can adjust any inventory
        if ($user->isPlatformAdmin()) {
            return true;
        }

        // Check if user has access to the store
        $store = $inventory->location->store;
        if (!$user->canAccessStore($store)) {
            return false;
        }

        // All store users (owner, admin, base) can adjust quantities
        return $user->canAccessStore($store);
    }

    /**
     * Determine if the user can set inventory quantity directly.
     * Only owners and admins can set quantities directly.
     */
    public function setQuantity(User $user, Inventory $inventory): bool
    {
        // Platform admins can set any inventory quantity
        if ($user->isPlatformAdmin()) {
            return true;
        }

        // Check if user has access to the store
        $store = $inventory->location->store;
        if (!$user->canAccessStore($store)) {
            return false;
        }

        // Only owners and admins can set quantities directly
        return $user->hasRole($store, 'owner') || $user->hasRole($store, 'admin');
    }
}

