<?php

namespace App\Support;

use App\Models\Card;
use App\Models\Edition;
use App\Models\Inventory;
use App\Models\Location;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

class InventoryUpdateQueue
{
    protected static function key(): string
    {
        $userId = Auth::id() ?? 'guest';
        return "inventory_update_queue_{$userId}";
    }

    public static function all(): array
    {
        return Session::get(static::key(), []);
    }

    public static function clear(): void
    {
        Session::forget(static::key());
    }

    public static function add(string $cardId, string $editionId, int $locationId, int $deltaQuantity, ?float $sellPrice = null, bool $isFoil = false): void
    {
        $card = Card::find($cardId);
        $location = Location::find($locationId);
        $edition = $editionId !== '' ? Edition::find($editionId) : null;

        if (! $card || ! $location) {
            return;
        }

        $inventory = Inventory::where('card_id', $cardId)
            ->where('edition_id', $editionId)
            ->where('location_id', $locationId)
            ->where('is_foil', $isFoil)
            ->first();

        $currentQuantity = $inventory?->quantity ?? 0;
        $existingSell = $inventory?->sell_price;

        $items = static::all();
        $key = implode(':', [$cardId, $editionId, $locationId, $isFoil ? 'foil' : 'normal']);

        $existing = $items[$key] ?? null;

        $newDelta = ($existing['delta_quantity'] ?? 0) + $deltaQuantity;
        $finalSell = $sellPrice !== null ? $sellPrice : ($existing['sell_price'] ?? $existingSell);

        $items[$key] = [
            'card_id' => $cardId,
            'card_name' => $card->name,
            'card_image_url' => $card->image_url,
            'card_prices' => $card->cardPrices()
                ->whereNotNull('market_price')
                ->orderByDesc('updated_at')
                ->take(5)
                ->get(['market_price', 'updated_at'])
                ->map(fn ($p) => [
                    'market_price' => $p->market_price,
                    'updated_at' => optional($p->updated_at)->toIso8601String(),
                ])->all(),
            'set_code' => $card->set_code,
            'card_number' => $card->card_number,
            'edition_id' => $editionId,
            'edition_label' => $edition ? static::formatEditionLabel($edition) : '—',
            'edition_image_url' => $edition?->image_url,
            'is_foil' => $isFoil,
            'location_id' => $locationId,
            'location_name' => $location->name,
            'current_quantity' => $currentQuantity,
            'delta_quantity' => $newDelta,
            'sell_price' => $finalSell,
        ];

        Session::put(static::key(), $items);
    }

    protected static function formatEditionLabel(Edition $edition): string
    {
        $rarity = $edition->rarity;
        $rarityText = match ($rarity) {
            1 => 'Common',
            2 => 'Uncommon',
            3 => 'Rare',
            4 => 'Super Rare',
            default => 'Rarity N/A',
        };

        $parts = [
            $edition->slug ?: $edition->collector_number,
            $edition->collector_number,
            $rarityText,
        ];

        return implode(' · ', array_filter($parts));
    }
}
