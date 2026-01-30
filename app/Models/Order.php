<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    protected $fillable = [
        'location_id',
        'shopify_order_id',
        'order_number',
        'customer_name',
        'customer_email',
        'total_amount',
        'subtotal_amount',
        'tax_amount',
        'currency',
        'status',
        'payment_status',
        'ordered_at',
        'fulfilled_at',
        'shopify_data',
    ];
    
    protected function casts(): array
    {
        return [
            'total_amount' => 'decimal:2',
            'subtotal_amount' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'ordered_at' => 'datetime',
            'fulfilled_at' => 'datetime',
            'shopify_data' => 'array',
        ];
    }
    
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }
    
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }
}
