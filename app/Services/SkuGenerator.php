<?php

namespace App\Services;

use App\Models\Card;
use App\Models\Edition;

class SkuGenerator
{
    /**
     * Generate a SKU for a card
     * Format: {SET_CODE}-{CARD_NUMBER}-{EDITION_SLUG?}-{FOIL?}
     * Examples:
     * - GA-001 (non-foil, default edition)
     * - GA-001-F (foil, default edition)
     * - GA-001-1ST (non-foil, 1st edition)
     * - GA-001-1ST-F (foil, 1st edition)
     */
    public static function generate(Card $card, ?Edition $edition = null, bool $isFoil = false): string
    {
        $parts = [];
        
        // Set code (required)
        if ($card->set_code) {
            $parts[] = strtoupper($card->set_code);
        }
        
        // Card number (required)
        if ($card->card_number) {
            $parts[] = str_pad($card->card_number, 3, '0', STR_PAD_LEFT);
        }
        
        // Edition slug (optional)
        if ($edition && $edition->slug) {
            $parts[] = strtoupper($edition->slug);
        }
        
        // Foil indicator (optional)
        if ($isFoil) {
            $parts[] = 'F';
        }
        
        $sku = implode('-', $parts);
        
        // Ensure uniqueness by appending number if needed
        return static::ensureUnique($sku, $card, $edition, $isFoil);
    }
    
    /**
     * Ensure SKU is unique by checking database
     */
    protected static function ensureUnique(string $baseSku, Card $card, ?Edition $edition, bool $isFoil): string
    {
        $sku = $baseSku;
        $counter = 1;
        
        // Check if SKU already exists for a different card
        while (Card::where('sku', $sku)
            ->where('id', '!=', $card->id)
            ->exists()) {
            $sku = $baseSku . '-' . $counter;
            $counter++;
        }
        
        return $sku;
    }
    
    /**
     * Generate SKU for inventory item
     */
    public static function generateForInventory(\App\Models\Inventory $inventory): string
    {
        $card = $inventory->card;
        $edition = $inventory->edition;
        // Ensure is_foil is a boolean (handle null case)
        $isFoil = (bool) ($inventory->is_foil ?? false);
        
        return static::generate($card, $edition, $isFoil);
    }
}

