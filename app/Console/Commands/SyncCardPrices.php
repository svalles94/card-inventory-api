<?php

namespace App\Console\Commands;

use App\Jobs\SyncCardPricesJob;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;

class SyncCardPrices extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'prices:sync 
                            {--full : Perform full sync (ignore last sync timestamp)}
                            {--sync : Dispatch job to queue}
                            {--test : Test API connection}
                            {--limit= : Limit number of pages to sync (for testing)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync card prices from production API';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $baseUrl = config('services.price_sync_api_base_url', 'https://api.tcgarchitect.com');
        $apiUrl = rtrim($baseUrl, '/') . '/api/v1/prices';

        // Test mode
        if ($this->option('test')) {
            return $this->testApiConnection($apiUrl);
        }

        // Get last sync timestamp from cache
        $lastSyncTimestamp = null;
        if (!$this->option('full')) {
            $lastSyncString = cache()->get('price_sync_last_timestamp');
            if ($lastSyncString) {
                try {
                    $lastSyncTimestamp = Carbon::parse($lastSyncString);
                } catch (\Exception $e) {
                    $this->warn("⚠️  Could not parse last sync timestamp, performing full sync");
                }
            }
        }

        // Dispatch job to queue
        if ($this->option('sync')) {
            $job = new SyncCardPricesJob(
                fullSync: $this->option('full'),
                lastSyncTimestamp: $lastSyncTimestamp
            );

            if ($this->option('full')) {
                $this->info('💰 Dispatching full price sync job to queue...');
            } else {
                $this->info('💰 Dispatching incremental price sync job to queue...');
                if ($lastSyncTimestamp) {
                    $this->line("   Last sync: {$lastSyncTimestamp->format('Y-m-d H:i:s')}");
                } else {
                    $this->line("   No previous sync found - will sync all prices");
                }
            }

            dispatch($job);

            $this->info('✅ Job dispatched successfully!');
            $this->line('   Run: php artisan queue:work');
            
            return Command::SUCCESS;
        }

        // Run synchronously (for testing)
        $this->warn('⚠️  Running sync synchronously (not recommended for production)');
        $this->line('   Use --sync flag to dispatch to queue instead');
        
        $limit = $this->option('limit') ? (int) $this->option('limit') : null;
        
        $job = new SyncCardPricesJob(
            fullSync: $this->option('full'),
            lastSyncTimestamp: $lastSyncTimestamp
        );

        // Add progress callback for console output
        $this->info('💰 Starting price sync...');
        $this->newLine();
        
        $stats = $job->handle($limit);

        $this->newLine();
        $this->info('📊 Sync Complete!');
        $this->table(
            ['Metric', 'Count'],
            [
                ['Total Processed', $stats['total']],
                ['Created', $stats['created']],
                ['Updated', $stats['updated']],
                ['Errors', $stats['errors']],
            ]
        );

        return Command::SUCCESS;
    }

    /**
     * Test API connection.
     */
    protected function testApiConnection(string $apiUrl): int
    {
        $this->info('💰 Testing price sync API connection...');
        $this->newLine();

        $testUrl = $apiUrl . '?page=1&per_page=10';
        $this->line("📡 Testing: {$testUrl}");

        try {
            $response = Http::timeout(10)->get($testUrl);

            if (!$response->successful()) {
                $this->error("❌ API request failed - Status: {$response->status()}");
                $this->line("   Response: " . $response->body());
                return Command::FAILURE;
            }

            $this->info("✅ API accessible - Status: {$response->status()}");

            $data = $response->json();

            if (!isset($data['data']) || !is_array($data['data'])) {
                $this->warn("⚠️  Unexpected response format");
                $this->line("   Response: " . json_encode($data, JSON_PRETTY_PRINT));
                return Command::FAILURE;
            }

            $prices = $data['data'];
            $count = count($prices);

            $this->newLine();
            $this->info("📊 Sample data retrieved successfully");
            $this->line("   • Found {$count} prices");

            if ($count > 0) {
                $sample = $prices[0];
                $this->line("   • Sample card: " . ($sample['card_name'] ?? 'N/A'));
                $this->line("   • Sample price: $" . ($sample['market_price'] ?? 'N/A'));
                
                if (isset($data['meta'])) {
                    $this->line("   • Total available: " . ($data['meta']['total'] ?? 'N/A'));
                    $this->line("   • Last page: " . ($data['meta']['last_page'] ?? 'N/A'));
                }
            }

            $this->newLine();
            $this->info('✅ API test successful!');

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error("❌ Connection error: {$e->getMessage()}");
            return Command::FAILURE;
        }
    }
}

