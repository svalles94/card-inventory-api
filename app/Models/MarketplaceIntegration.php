<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketplaceIntegration extends Model
{
    protected $fillable = [
        'store_id',
        'marketplace',
        'enabled',
        'credentials',
        'settings',
        'last_sync_at',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'credentials' => 'encrypted:array',
        'settings' => 'array',
        'last_sync_at' => 'datetime',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
    
    // Helper methods for marketplace types
    public function isShopify(): bool
    {
        return $this->marketplace === 'shopify';
    }
    
    public function isEbay(): bool
    {
        return $this->marketplace === 'ebay';
    }
    
    public function isTcgPlayer(): bool
    {
        return $this->marketplace === 'tcgplayer';
    }
}
