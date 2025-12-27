<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'is_platform_admin',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_platform_admin' => 'boolean',
        ];
    }

    /**
     * Check if user is a platform admin
     */
    public function isPlatformAdmin(): bool
    {
        return $this->is_platform_admin === true;
    }

    /**
     * Get all stores this user belongs to
     */
    public function stores()
    {
        return $this->belongsToMany(Store::class, 'store_user_roles')
            ->withPivot('role')
            ->withTimestamps();
    }

    /**
     * Locations the user is explicitly assigned to (optional scoping inside a store).
     */
    public function locations(): BelongsToMany
    {
        return $this->belongsToMany(Location::class, 'location_user')
            ->withTimestamps();
    }

    /**
     * Get all store role assignments
     */
    public function storeRoles()
    {
        return $this->hasMany(StoreUserRole::class);
    }

    /**
     * Check if user has a specific role in a store
     */
    public function hasRole(Store $store, string $role): bool
    {
        return $this->storeRoles()
            ->where('store_id', $store->id)
            ->where('role', $role)
            ->exists();
    }

    /**
     * Check if user can access a store
     */
    public function canAccessStore(Store $store): bool
    {
        // Platform admins can access all stores
        if ($this->isPlatformAdmin()) {
            return true;
        }

        // Check if user has any role in this store
        return $this->storeRoles()
            ->where('store_id', $store->id)
            ->exists();
    }

    /**
     * Check if user can access a location (must belong to its store).
     */
    public function canAccessLocation(Location $location): bool
    {
        return $this->canAccessStore($location->store);
    }

    /**
     * Get the current store from session
     */
    public function currentStore(): ?Store
    {
        $storeId = session('current_store_id');
        if (!$storeId) {
            return null;
        }

        $store = Store::find($storeId);
        if (!$store || !$this->canAccessStore($store)) {
            return null;
        }

        return $store;
    }

    /**
     * Get the current location from session, ensuring it belongs to the current store.
     */
    public function currentLocation(): ?Location
    {
        $locationId = session('current_location_id');

        if (!$locationId) {
            return null;
        }

        $location = Location::with('store')->find($locationId);

        if (!$location) {
            return null;
        }

        $currentStore = $this->currentStore();

        if (!$currentStore || $location->store_id !== $currentStore->id) {
            return null;
        }

        if (! $this->canAccessLocation($location)) {
            return null;
        }

        return $location;
    }

    /**
     * Determine if the user can access the Filament admin panel.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        // Only allow platform admins to access the admin panel
        if ($panel->getId() === 'admin') {
            return $this->isPlatformAdmin();
        }

        // For store panel, check if user has access to any store
        if ($panel->getId() === 'store') {
            // Platform admins can access store panel (to view stores)
            if ($this->isPlatformAdmin()) {
                return true;
            }
            
            // Regular users need to belong to at least one store
            return $this->stores()->count() > 0;
        }

        return true;
    }
}
