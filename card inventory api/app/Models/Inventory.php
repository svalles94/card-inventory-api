<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Inventory extends Model
{
    use HasFactory;

    protected $table = 'inventory';

    protected $fillable = [
        'location_id',
        'card_id',
        'edition_id',
        'is_foil',
        'quantity',
        'custom_price',
        'buy_price',
        'sell_price',
        'market_price',
        'shopify_location_id',
        'shopify_inventory_level_id',
        'shopify_variant_id',
        'last_synced_at',
        'sync_status',
        'sync_error',
    ];

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function card(): BelongsTo
    {
        return $this->belongsTo(Card::class, 'card_id', 'id');
    }
    
    protected function casts(): array
    {
        return [
            'card_id' => 'string',
            'edition_id' => 'string',
            'is_foil' => 'boolean',
            'buy_price' => 'decimal:2',
            'sell_price' => 'decimal:2',
            'market_price' => 'decimal:2',
            'last_synced_at' => 'datetime',
        ];
    }

    public function edition(): BelongsTo
    {
        return $this->belongsTo(Edition::class, 'edition_id', 'id');
    }
}

