<?php

namespace App\Http\Controllers\Store;

use App\Models\Card;
use App\Models\Edition;
use App\Models\Inventory;
use App\Support\InventoryUpdateQueue;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class CardGridController extends \App\Http\Controllers\Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        
        if (!$user) {
            abort(403, 'Unauthorized');
        }

        $store = $user->currentStore();
        
        if (!$store) {
            if ($request->wantsJson()) {
                return response()->json([
                    'error' => 'No store selected. Please select a store first.',
                ], 400);
            }
            return redirect()->route('filament.store.pages.store-select');
        }

        $location = $user->currentLocation();
        
        // If no location is set, try to get the first location from the current store
        if (!$location) {
            $location = $store->locations()->first();
            if ($location) {
                session(['current_location_id' => $location->id]);
                // Also set it in the app instance for this request
                app()->instance('current_location', $location);
            }
        }

        if (!$location) {
            if ($request->wantsJson()) {
                return response()->json([
                    'error' => 'No location found. Please create a location first.',
                ], 400);
            }
            // Redirect to locations page to create one
            return redirect()->route('filament.store.resources.store-locations.index')
                ->with('error', 'Please create a location first before viewing cards.');
        }

        // Get cards with their latest editions, ordered by last_update
        // Filter cards that have editions with inventory at this location OR all cards (depending on requirements)
        $cards = Card::with(['editions' => function ($query) {
            $query->orderByDesc('last_update')
                  ->orderByDesc('created_at')
                  ->limit(1);
        }])
        ->whereHas('editions', function ($query) {
            $query->orderByDesc('last_update')
                  ->orderByDesc('created_at');
        })
        ->orderByDesc('last_update')
        ->orderByDesc('created_at')
        ->limit(100) // Limit for performance
        ->get()
        ->map(function ($card) use ($location) {
            $latestEdition = $card->editions->first();
            
            // Check if card has inventory at this location
            $hasInventory = Inventory::where('card_id', $card->id)
                ->where('location_id', $location->id)
                ->where('quantity', '>', 0)
                ->exists();
            
            return [
                'id' => $card->id,
                'name' => $card->name,
                'image_url' => $card->image_url,
                'edition_id' => $latestEdition?->id,
                'edition_image_url' => $latestEdition?->image_url,
                'in_stock' => $hasInventory,
            ];
        });

        if ($request->wantsJson()) {
            return response()->json([
                'cards' => $cards,
                'location' => [
                    'id' => $location->id,
                    'name' => $location->name,
                ],
            ]);
        }

        return view('store.cards-grid', [
            'cards' => $cards,
            'location' => $location,
        ]);
    }

    public function addToQueue(Request $request)
    {
        $user = Auth::user();
        
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $store = $user->currentStore();
        
        if (!$store) {
            return response()->json([
                'error' => 'No store selected',
            ], 400);
        }

        $location = $user->currentLocation();
        
        // If no location is set, try to get the first location from the current store
        if (!$location) {
            $location = $store->locations()->first();
            if ($location) {
                session(['current_location_id' => $location->id]);
            }
        }

        if (!$location) {
            return response()->json([
                'error' => 'No location found. Please create a location first.',
            ], 400);
        }

        $request->validate([
            'card_id' => 'required|string',
            'edition_id' => 'nullable|string',
            'delta_quantity' => 'required|integer|min:1',
            'is_foil' => 'boolean',
        ]);

        InventoryUpdateQueue::add(
            $request->card_id,
            $request->edition_id ?? '',
            $location->id,
            $request->delta_quantity,
            $request->sell_price ?? null,
            $request->boolean('is_foil', false)
        );

        return response()->json([
            'success' => true,
            'message' => 'Card added to update queue',
        ]);
    }
}

