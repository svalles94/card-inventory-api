<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Card extends Model
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
        'name',
        'slug',
        'image',
        'image_filename',
        'cost_memory',
        'cost_reserve',
        'durability',
        'power',
        'life',
        'level',
        'speed',
        'effect',
        'effect_raw',
        'effect_html',
        'flavor',
        'illustrator',
        'types',
        'subtypes',
        'classes',
        'elements',
        'rule',
        'referenced_by',
        'references',
        'rarity',
        'legality',
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
            'types' => 'array',
            'subtypes' => 'array',
            'classes' => 'array',
            'elements' => 'array',
            'rule' => 'array',
            'referenced_by' => 'array',
            'references' => 'array',
            'cost_memory' => 'integer',
            'cost_reserve' => 'integer',
            'durability' => 'integer',
            'power' => 'integer',
            'life' => 'integer',
            'level' => 'integer',
            'speed' => 'integer',
            'rarity' => 'integer',
            'created_at' => 'datetime',
            'last_update' => 'datetime',
        ];
    }
}

