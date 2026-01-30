<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Store extends Model
{
    use HasFactory;

    protected $fillable = [
        'owner_id',
        'name',
        'address',
        'phone',
        'email',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function locations(): HasMany
    {
        return $this->hasMany(Location::class);
    }
    
    public function marketplaceIntegrations(): HasMany
    {
        return $this->hasMany(MarketplaceIntegration::class);
    }

    /**
     * Get all users with access to this store
     */
    public function users()
    {
        return $this->belongsToMany(User::class, 'store_user_roles')
            ->withPivot('role')
            ->withTimestamps();
    }

    /**
     * Get all role assignments for this store
     */
    public function roles(): HasMany
    {
        return $this->hasMany(StoreUserRole::class);
    }

    /**
     * Get users with a specific role
     */
    public function usersWithRole(string $role)
    {
        return $this->users()->wherePivot('role', $role);
    }
}

