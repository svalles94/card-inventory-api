<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Edition extends Model
{
    use HasFactory;

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'id';

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = false;

    /**
     * The "type" of the primary key ID.
     *
     * @var string
     */
    protected $keyType = 'string';

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'id',
        'card_id',
        'set_id',
        'collector_number',
        'configuration',
        'effect',
        'effect_html',
        'effect_raw',
        'flavor',
        'illustrator',
        'orientation',
        'other_orientations',
        'image',
        'rarity',
        'slug',
        'tcgplayer_product_id',
        'tcgplayer_sku',
        'market_price',
        'tcgplayer_low_price',
        'tcgplayer_high_price',
        'last_price_update',
        'created_at',
        'last_update',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'other_orientations' => 'array',
            'rarity' => 'integer',
            'tcgplayer_product_id' => 'integer',
            'market_price' => 'decimal:2',
            'tcgplayer_low_price' => 'decimal:2',
            'tcgplayer_high_price' => 'decimal:2',
            'last_price_update' => 'datetime',
            'created_at' => 'datetime',
            'last_update' => 'datetime',
        ];
    }

    /**
     * Get the card that owns the edition.
     */
    public function card(): BelongsTo
    {
        return $this->belongsTo(Card::class);
    }

    /**
     * Get the set that owns the edition.
     */
    public function set(): BelongsTo
    {
        return $this->belongsTo(Set::class);
    }

    /**
     * Get the card prices for the edition.
     */
    public function cardPrices(): HasMany
    {
        return $this->hasMany(CardPrice::class);
    }

    /**
     * Alias for cardPrices relationship.
     */
    public function prices(): HasMany
    {
        return $this->cardPrices();
    }
}

