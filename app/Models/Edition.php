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

    /**
     * Get the full image URL for this edition.
     * If image is already a full URL, return it. Otherwise, prepend the Grand Archive API base URL.
     */
    public function getImageUrlAttribute(): ?string
    {
        if (!$this->image) {
            return null;
        }

        // If it's already a full URL, return it as-is
        if (filter_var($this->image, FILTER_VALIDATE_URL)) {
            return $this->image;
        }

        // Otherwise, construct the full URL using the Grand Archive API base
        $baseUrl = rtrim('https://api.gatcg.com', '/');
        $imagePath = ltrim($this->image, '/');
        
        return $baseUrl . '/' . $imagePath;
    }
}

