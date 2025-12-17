<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use App\Models\Card;

class FetchGrandArchiveData extends Command
{
    protected $signature = 'grand-archive:fetch 
                            {--cards : Fetch cards data} 
                            {--test : Test API endpoints} 
                            {--limit= : Limit number of cards to fetch} 
                            {--update : Update existing cards with missing data} 
                            {--dry-run : Show what would be done without making changes}';

    protected $description = 'Fetch cards data from Grand Archive API';

    private $apiBase = 'https://api.gatcg.com';

    public function handle()
    {
        $isDryRun = $this->option('dry-run');

        if ($isDryRun) {
            $this->info('🚀 DRY RUN MODE - No changes will be made to the database');
            $this->info('Starting Grand Archive data fetch simulation...');
        } else {
            $this->info('Starting Grand Archive data fetch...');
        }

        if ($this->option('test')) {
            $this->testApiEndpoints();
            return;
        }

        if ($this->option('cards')) {
            $this->fetchCards();
        } else {
            $this->info('No action specified. Use --cards to fetch cards data.');
            $this->info('Use --help to see all available options.');
        }

        if ($isDryRun) {
            $this->info('✅ DRY RUN COMPLETED - No changes were made to the database');
        } else {
            $this->info('Grand Archive data fetch completed!');
        }
    }

    private function testApiEndpoints()
    {
        $this->info('Testing Grand Archive API endpoints...');
        $endpoints = [
            'cards/search' => '/cards/search?page_size=1',
            'cards/autocomplete' => '/cards/autocomplete?name=test',
            'cards/random' => '/cards/random?amount=1'
        ];

        foreach ($endpoints as $name => $endpoint) {
            try {
                $response = Http::timeout(10)
                    ->withoutVerifying()
                    ->get($this->apiBase . $endpoint);

                $status = $response->successful() ? '✅' : '❌';
                $this->line("  {$status} {$name} - Status: {$response->status()}");

                if ($response->successful()) {
                    $data = $response->json();
                    if (is_array($data)) {
                        $count = count($data);
                        $this->line("    Found {$count} items");

                        if ($name === 'cards/search' && isset($data['data']) && count($data['data']) > 0) {
                            $card = $data['data'][0];
                            $this->line("    Card sample: {$card['name']} (UUID: {$card['uuid']})");
                        }
                    }
                }
            } catch (\Exception $e) {
                $this->line("  ❌ {$name} - Error: " . $e->getMessage());
            }
        }
    }

    private function fetchCards()
    {
        $this->info('Fetching cards data from Grand Archive API...');
        
        try {
            $limit = (int) $this->option('limit') ?: 50; // Default to 50 if not specified
            $pageSize = min(50, $limit);
            $totalProcessed = 0;
            $cardsCreated = 0;
            $cardsUpdated = 0;
            $cardsSkipped = 0;
            $errors = 0;
            $isDryRun = $this->option('dry-run');
            $isUpdateMode = $this->option('update');
            $dryRunChanges = [];

            for ($page = 1; $totalProcessed < $limit; $page++) {
                $response = Http::timeout(30)
                    ->withoutVerifying()
                    ->get($this->apiBase . '/cards/search', [
                        'page' => $page,
                        'page_size' => $pageSize,
                        'sort' => 'name'
                    ]);

                if (!$response->successful()) {
                    $this->error("Failed to fetch cards from page {$page}");
                    break;
                }

                $data = $response->json();
                $cards = $data['data'] ?? [];

                if (empty($cards)) {
                    break;
                }

                $this->info("Processing page {$page}, found " . count($cards) . " cards");

                foreach ($cards as $cardData) {
                    if ($totalProcessed >= $limit) {
                        break;
                    }

                    // Check if the card already exists in our database by UUID
                    $existingCard = Card::find($cardData['uuid']);

                    if ($existingCard) {
                        if ($isUpdateMode) {
                            // Update existing card
                            try {
                                if ($isDryRun) {
                                    // Collect changes for summary display
                                    $updateData = $this->prepareCardData($cardData);
                                    $changes = $this->getFieldChanges($existingCard, $updateData);

                                    if (!empty($changes)) {
                                        $dryRunChanges[] = [
                                            'name' => $cardData['name'],
                                            'uuid' => $cardData['uuid'],
                                            'changes' => $changes
                                        ];
                                    }
                                } else {
                                    // Actually update the card
                                    $updateData = $this->prepareCardData($cardData);
                                    $existingCard->update($updateData);
                                    $cardsUpdated++;
                                    $this->line("Updated existing card: {$cardData['name']} (UUID: {$cardData['uuid']})");
                                }
                            } catch (\Exception $e) {
                                $this->warn("Error updating card {$cardData['name']}: " . $e->getMessage());
                                $errors++;
                                $totalProcessed++;
                                continue;
                            }
                        } else {
                            // Skip existing card (original behavior)
                            $cardsSkipped++;
                            if ($isDryRun) {
                                $dryRunChanges[] = [
                                    'name' => $cardData['name'],
                                    'uuid' => $cardData['uuid'],
                                    'action' => 'skip',
                                    'reason' => 'Card already exists and not in update mode'
                                ];
                            }
                        }
                        $totalProcessed++;
                        continue;
                    }

                    // Create new card
                    try {
                        if ($isDryRun) {
                            $dryRunChanges[] = [
                                'name' => $cardData['name'],
                                'uuid' => $cardData['uuid'],
                                'action' => 'create',
                                'data' => $this->prepareCardData($cardData)
                            ];
                        } else {
                            $card = Card::create($this->prepareCardData($cardData));
                            $cardsCreated++;
                            $this->line("Created new card: {$cardData['name']} (UUID: {$cardData['uuid']})");
                        }
                    } catch (\Exception $e) {
                        $this->warn("Error creating card {$cardData['name']}: " . $e->getMessage());
                        $errors++;
                        $totalProcessed++;
                        continue;
                    }

                    $totalProcessed++;
                }

                $this->info("Processed page {$page}, total cards: {$totalProcessed}");
            }

            // Display dry-run summary if in dry-run mode
            if ($isDryRun && !empty($dryRunChanges)) {
                $this->displayDryRunSummary($dryRunChanges);
            }

            $this->info("Successfully processed {$totalProcessed} cards");
            if (!$isDryRun) {
                $this->info("  • Created: {$cardsCreated}");
                $this->info("  • Updated: {$cardsUpdated}");
                $this->info("  • Skipped: {$cardsSkipped}");
                $this->info("  • Errors: {$errors}");
            }
        } catch (\Exception $e) {
            $this->error("Error fetching cards: " . $e->getMessage());
            $this->error($e->getTraceAsString());
        }
    }

    private function prepareCardData($cardData)
    {
        // Clean and escape the effect_html field
        $effectHtml = $cardData['effect_html'] ?? null;
        if ($effectHtml && is_string($effectHtml)) {
            // Remove control characters but keep the HTML structure
            $effectHtml = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $effectHtml);
        }

        // Extract edition data for illustrator, image, rarity from first edition
        $illustrator = null;
        $image = null;
        $imageFilename = null;
        $rarity = null;

        if (isset($cardData['editions']) && is_array($cardData['editions']) && count($cardData['editions']) > 0) {
            $edition = $cardData['editions'][0];
            $illustrator = $edition['illustrator'] ?? null;
            $image = $edition['image'] ?? null;
            $rarity = $edition['rarity'] ?? null;

            // Extract filename from image path
            if ($image) {
                $imageFilename = basename(parse_url($image, PHP_URL_PATH));
            }
        }

        // Handle legality - convert array to JSON string if it's an array
        $legality = $cardData['legality'] ?? null;
        if (is_array($legality)) {
            $legality = json_encode($legality);
        }

        return [
            'id' => $cardData['uuid'],
            'name' => $cardData['name'] ?? null,
            'slug' => $cardData['slug'] ?? null,
            'image' => $image,
            'image_filename' => $imageFilename,
            'cost_memory' => $this->getIntegerValue($cardData['cost_memory'] ?? null),
            'cost_reserve' => $this->getIntegerValue($cardData['cost_reserve'] ?? null),
            'durability' => $this->getIntegerValue($cardData['durability'] ?? null),
            'power' => $this->getIntegerValue($cardData['power'] ?? null),
            'life' => $this->getIntegerValue($cardData['life'] ?? null),
            'level' => $this->getIntegerValue($cardData['level'] ?? null),
            'speed' => $this->getIntegerValue($cardData['speed'] ?? null),
            'effect' => $this->getStringValue($cardData['effect'] ?? null),
            'effect_raw' => $this->getStringValue($cardData['effect_raw'] ?? null),
            'effect_html' => $effectHtml,
            'flavor' => $this->getStringValue($cardData['flavor'] ?? null),
            'illustrator' => $illustrator,
            'types' => $cardData['types'] ?? null,
            'subtypes' => $cardData['subtypes'] ?? null,
            'classes' => $cardData['classes'] ?? null,
            'elements' => $cardData['elements'] ?? null,
            'rule' => $cardData['rule'] ?? null,
            'referenced_by' => $cardData['referenced_by'] ?? null,
            'references' => $cardData['references'] ?? null,
            'rarity' => $this->getIntegerValue($rarity),
            'legality' => $legality,
            'created_at' => isset($cardData['created_at']) ? $cardData['created_at'] : now(),
            'last_update' => isset($cardData['last_update']) ? $cardData['last_update'] : now(),
        ];
    }

    private function getIntegerValue($value)
    {
        if ($value === null || is_array($value)) {
            return null;
        }
        return (int) $value;
    }

    private function getStringValue($value)
    {
        if ($value === null || is_array($value)) {
            return null;
        }
        return (string) $value;
    }

    private function getFieldChanges($existingCard, $updateData)
    {
        $changedFields = [];

        foreach ($updateData as $field => $newValue) {
            // Skip id and timestamps for comparison
            if (in_array($field, ['id', 'created_at'])) {
                continue;
            }

            $currentValue = $existingCard->$field;

            // Handle null comparisons
            if ($currentValue === null && $newValue === null) {
                continue;
            }

            if ($currentValue === null && $newValue !== null) {
                $changedFields[$field] = [
                    'current' => 'null',
                    'new' => $this->formatValue($newValue)
                ];
                continue;
            }

            if ($currentValue !== null && $newValue === null) {
                $changedFields[$field] = [
                    'current' => $this->formatValue($currentValue),
                    'new' => 'null'
                ];
                continue;
            }

            // Handle array/JSON comparisons
            if (is_array($currentValue) || is_array($newValue)) {
                $currentJson = is_array($currentValue) ? json_encode($currentValue) : $currentValue;
                $newJson = is_array($newValue) ? json_encode($newValue) : $newValue;

                if ($currentJson !== $newJson) {
                    $changedFields[$field] = [
                        'current' => $currentJson,
                        'new' => $newJson
                    ];
                }
                continue;
            }

            // Regular string/number comparison
            if ($currentValue != $newValue) {
                $changedFields[$field] = [
                    'current' => $this->formatValue($currentValue),
                    'new' => $this->formatValue($newValue)
                ];
            }
        }

        return $changedFields;
    }

    private function formatValue($value)
    {
        if (is_array($value)) {
            return json_encode($value);
        }
        if (is_string($value) && strlen($value) > 100) {
            return substr($value, 0, 100) . '...';
        }
        return $value;
    }

    private function displayDryRunSummary($dryRunChanges)
    {
        $this->info('');
        $this->info('📋 DRY RUN SUMMARY:');
        $this->info('=' . str_repeat('=', 60));

        $cardsToCreate = 0;
        $cardsToUpdate = 0;
        $cardsSkipped = 0;

        foreach ($dryRunChanges as $change) {
            if (isset($change['action'])) {
                if ($change['action'] === 'create') {
                    $cardsToCreate++;
                    $this->info('');
                    $this->info("🆕 WOULD CREATE: {$change['name']} (UUID: {$change['uuid']})");
                } elseif ($change['action'] === 'skip') {
                    $cardsSkipped++;
                }
            } else {
                $cardsToUpdate++;
                $this->info('');
                $this->info("🔄 WOULD UPDATE: {$change['name']} (UUID: {$change['uuid']})");
                foreach ($change['changes'] as $field => $values) {
                    $this->line("  • {$field}:");
                    $this->line("    Current: " . $this->formatValue($values['current']));
                    $this->line("    New: " . $this->formatValue($values['new']));
                }
            }
        }

        $this->info('');
        $this->info('📊 SUMMARY STATISTICS:');
        $this->info("  • Cards to create: {$cardsToCreate}");
        $this->info("  • Cards to update: {$cardsToUpdate}");
        $this->info("  • Cards to skip: {$cardsSkipped}");
        $this->info('');
        $this->info('=' . str_repeat('=', 60));
    }
}

