<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CardPrice extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'card_id',
        'edition_id',
        'tcgplayer_product_id',
        'sub_type_name',
        'market_price',
        'low_price',
        'high_price',
        'last_updated',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tcgplayer_product_id' => 'integer',
            'market_price' => 'decimal:2',
            'low_price' => 'decimal:2',
            'high_price' => 'decimal:2',
            'last_updated' => 'datetime',
        ];
    }

    /**
     * Get the card that owns the price.
     */
    public function card(): BelongsTo
    {
        return $this->belongsTo(Card::class);
    }

    /**
     * Get the edition that owns the price.
     */
    public function edition(): BelongsTo
    {
        return $this->belongsTo(Edition::class);
    }
}

