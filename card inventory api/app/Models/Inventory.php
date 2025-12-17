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
        'quantity',
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
        ];
    }
}

