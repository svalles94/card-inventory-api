<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StoreUserRole extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'store_id',
        'role',
    ];

    protected function casts(): array
    {
        return [
            'role' => 'string',
        ];
    }

    /**
     * Get the user that owns this role assignment
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the store this role is for
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * Check if role is owner
     */
    public function isOwner(): bool
    {
        return $this->role === 'owner';
    }

    /**
     * Check if role is admin
     */
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    /**
     * Check if role is base
     */
    public function isBase(): bool
    {
        return $this->role === 'base';
    }
}

