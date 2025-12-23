<?php

namespace App\Jobs;

use App\Models\Card;
use App\Models\CardPrice;
use App\Models\Edition;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class SyncCardPricesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public bool $fullSync = false,
        public ?Carbon $lastSyncTimestamp = null
    ) {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(?int $pageLimit = null): array
    {
        $baseUrl = config('services.price_sync_api_base_url', 'https://api.tcgarchitect.com');
        $apiUrl = rtrim($baseUrl, '/') . '/api/v1/prices';
        
        $stats = [
            'created' => 0,
            'updated' => 0,
            'errors' => 0,
            'total' => 0,
        ];

        $page = 1;
        $perPage = 500;
        $hasMorePages = true;

        Log::info('💰 Syncing card prices from production API...', [
            'full_sync' => $this->fullSync,
            'last_sync' => $this->lastSyncTimestamp?->toIso8601String(),
        ]);

        while ($hasMorePages) {
            try {
                $params = [
                    'page' => $page,
                    'per_page' => $perPage,
                ];

                // Add updated_since parameter for incremental syncs
                if (!$this->fullSync && $this->lastSyncTimestamp) {
                    $params['updated_since'] = $this->lastSyncTimestamp->toIso8601String();
                }

                Log::info("📡 Fetching page {$page}...", ['params' => $params]);

                $response = Http::timeout(30)->get($apiUrl, $params);

                if (!$response->successful()) {
                    Log::error("❌ API request failed", [
                        'status' => $response->status(),
                        'body' => $response->body(),
                    ]);
                    $stats['errors']++;
                    break;
                }

                $data = $response->json();

                if (!isset($data['data']) || !is_array($data['data'])) {
                    Log::warning("⚠️ Invalid response format", ['data' => $data]);
                    break;
                }

                $prices = $data['data'];
                $pageStats = $this->syncPrices($prices);
                
                $stats['created'] += $pageStats['created'];
                $stats['updated'] += $pageStats['updated'];
                $stats['errors'] += $pageStats['errors'];
                $stats['total'] += count($prices);

                Log::info("✅ Processed " . count($prices) . " prices", [
                    'created' => $pageStats['created'],
                    'updated' => $pageStats['updated'],
                    'errors' => $pageStats['errors'],
                ]);

                // Check if there are more pages
                $hasMorePages = isset($data['links']['next']) && !empty($data['links']['next']);
                if (!$hasMorePages && isset($data['meta'])) {
                    $hasMorePages = $data['meta']['current_page'] < $data['meta']['last_page'];
                }

                // Apply page limit for testing
                if ($pageLimit !== null && $page >= $pageLimit) {
                    $hasMorePages = false;
                    Log::info("📊 Reached page limit ({$pageLimit}), stopping sync");
                }

                $page++;

            } catch (\Exception $e) {
                Log::error("❌ Error fetching prices", [
                    'page' => $page,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                $stats['errors']++;
                
                // If it's a network error, we might want to retry
                // For now, break and let the job retry mechanism handle it
                if ($this->attempts() < 3) {
                    throw $e; // Re-throw to trigger retry
                }
                break;
            }
        }

        // Update last sync timestamp in config/cache
        if ($stats['total'] > 0 || $this->fullSync) {
            cache()->forever('price_sync_last_timestamp', now()->toIso8601String());
            config(['price_sync.last_sync' => now()->toIso8601String()]);
        }

        Log::info("📊 Sync Complete!", [
            'total' => $stats['total'],
            'created' => $stats['created'],
            'updated' => $stats['updated'],
            'errors' => $stats['errors'],
            'last_sync' => now()->toIso8601String(),
        ]);

        return $stats;
    }

    /**
     * Sync prices from API response to database.
     */
    protected function syncPrices(array $prices): array
    {
        $stats = [
            'created' => 0,
            'updated' => 0,
            'errors' => 0,
        ];

        foreach ($prices as $priceData) {
            try {
                $cardId = $priceData['card_id'];
                $editionId = $priceData['edition_id'] ?? null;

                // Check if card exists (required by foreign key)
                if (!Card::where('id', $cardId)->exists()) {
                    Log::warning("⚠️  Card not found, skipping price", [
                        'card_id' => $cardId,
                        'card_name' => $priceData['card_name'] ?? 'N/A',
                    ]);
                    $stats['errors']++;
                    continue;
                }

                // Check if edition exists (if provided, required by foreign key)
                if ($editionId && !Edition::where('id', $editionId)->exists()) {
                    Log::warning("⚠️  Edition not found, skipping price", [
                        'edition_id' => $editionId,
                        'card_id' => $cardId,
                    ]);
                    $stats['errors']++;
                    continue;
                }

                // Prepare the unique key for updateOrCreate
                $uniqueKey = [
                    'card_id' => $cardId,
                    'edition_id' => $editionId,
                    'tcgplayer_product_id' => (int) $priceData['tcgplayer_product_id'],
                    'sub_type_name' => $priceData['sub_type_name'],
                ];

                // Prepare the attributes to update
                $attributes = [
                    'market_price' => $priceData['market_price'] ? (float) $priceData['market_price'] : null,
                    'low_price' => $priceData['low_price'] ? (float) $priceData['low_price'] : null,
                    'high_price' => $priceData['high_price'] ? (float) $priceData['high_price'] : null,
                    'last_updated' => isset($priceData['last_updated']) 
                        ? Carbon::parse($priceData['last_updated']) 
                        : null,
                ];

                // Check if record exists
                $exists = CardPrice::where($uniqueKey)->exists();

                // Use updateOrCreate to sync
                CardPrice::updateOrCreate($uniqueKey, $attributes);

                if ($exists) {
                    $stats['updated']++;
                } else {
                    $stats['created']++;
                }

            } catch (\Exception $e) {
                Log::error("❌ Error syncing price", [
                    'price_data' => $priceData,
                    'error' => $e->getMessage(),
                ]);
                $stats['errors']++;
            }
        }

        return $stats;
    }
}

