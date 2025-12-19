<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use App\Models\Card;
use App\Models\Set;
use App\Models\Edition;

class FetchGrandArchiveData extends Command
{
    protected $signature = 'grand-archive:fetch 
                            {--sets : Fetch sets data} 
                            {--editions : Fetch editions data} 
                            {--cards : Fetch cards data} 
                            {--all : Fetch all data (sets, cards, editions)}
                            {--test : Test API endpoints} 
                            {--limit= : Limit number of cards to fetch} 
                            {--update : Update existing cards with missing data} 
                            {--dry-run : Show what would be done without making changes}';

    protected $description = 'Fetch sets, editions, and cards data from Grand Archive API';

    private $apiBase = 'https://api.gatcg.com';
    private $summaries = [];

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

        if ($this->option('all') || $this->option('sets')) {
            $this->fetchSets();
        }

        if ($this->option('all') || $this->option('cards')) {
            $this->fetchCards();
        }

        if ($this->option('all') || $this->option('editions')) {
            $this->fetchEditions();
        }

        // Display all summaries at the end
        $this->displayFinalSummary();

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
                            if (isset($card['editions']) && count($card['editions']) > 0) {
                                $edition = $card['editions'][0];
                                $this->line("    Edition sample: {$edition['collector_number']} - Set: {$edition['set']['name']}");
                            }
                        }
                    }
                }
            } catch (\Exception $e) {
                $this->line("  ❌ {$name} - Error: " . $e->getMessage());
            }
        }
    }

    private function fetchSets()
    {
        $this->info('Fetching sets data from cards...');

        try {
            // If --all is used and no limit specified, process all cards (unlimited)
            $limit = $this->option('limit') ? (int) $this->option('limit') : ($this->option('all') ? PHP_INT_MAX : 50);
            $pageSize = min(50, $limit === PHP_INT_MAX ? 50 : $limit);
            $totalProcessed = 0;
            $setsCreated = 0;
            $isDryRun = $this->option('dry-run');
            $processedSetIds = []; // Track which sets we've already seen in this run

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

                foreach ($cards as $cardData) {
                    if ($totalProcessed >= $limit) break;

                    if (isset($cardData['editions']) && is_array($cardData['editions'])) {
                        foreach ($cardData['editions'] as $editionData) {
                            if (isset($editionData['set'])) {
                                $setData = $editionData['set'];
                                $setId = $setData['id'];
                                
                                // Skip if we've already processed this set in this run
                                if (isset($processedSetIds[$setId])) {
                                    continue;
                                }
                                
                                $processedSetIds[$setId] = true;
                                
                                if ($isDryRun) {
                                    $existingSet = Set::find($setId);
                                    if (!$existingSet) {
                                        $this->line("Would create set: {$setData['name']} ({$setData['prefix']})");
                                        $setsCreated++;
                                    }
                                } else {
                                    $set = Set::updateOrCreate(
                                        ['id' => $setId],
                                        [
                                            'name' => $setData['name'],
                                            'prefix' => $setData['prefix'] ?? null,
                                            'language' => $setData['language'] ?? null,
                                            'release_date' => $setData['release_date'] ?? null,
                                            'image' => $setData['image'] ?? null,
                                            'created_at' => $setData['created_at'] ?? now(),
                                            'last_update' => $setData['last_update'] ?? now()
                                        ]
                                    );

                                    if ($set->wasRecentlyCreated) {
                                        $setsCreated++;
                                        $this->line("Created set: {$set->name} ({$set->prefix})");
                                    }
                                }
                            }
                        }
                    }

                    $totalProcessed++;
                }

                $this->info("Processed page {$page}, total cards: {$totalProcessed}");
            }

            // Store summary instead of displaying immediately
            $this->summaries['sets'] = [
                'total_processed' => $totalProcessed,
                'sets_created' => $setsCreated,
                'is_dry_run' => $isDryRun
            ];
        } catch (\Exception $e) {
            $this->error("Error fetching sets: " . $e->getMessage());
            // Store empty summary on error
            $this->summaries['sets'] = [
                'total_processed' => 0,
                'sets_created' => 0,
                'is_dry_run' => $this->option('dry-run')
            ];
        }
    }

    private function fetchCards()
    {
        $this->info('Fetching cards data from Grand Archive API...');
        
        try {
            // If --all is used and no limit specified, process all cards (unlimited)
            $limit = $this->option('limit') ? (int) $this->option('limit') : ($this->option('all') ? PHP_INT_MAX : 50);
            $pageSize = min(50, $limit === PHP_INT_MAX ? 50 : $limit);
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

                    // Also create sets and editions for this card (if not dry run)
                    if (!$isDryRun && isset($cardData['editions']) && is_array($cardData['editions'])) {
                        foreach ($cardData['editions'] as $editionData) {
                            if (isset($editionData['set'])) {
                                $setData = $editionData['set'];
                                
                                $set = Set::updateOrCreate(
                                    ['id' => $setData['id']],
                                    [
                                        'name' => $setData['name'],
                                        'prefix' => $setData['prefix'] ?? null,
                                        'language' => $setData['language'] ?? null,
                                        'release_date' => $setData['release_date'] ?? null,
                                        'image' => $setData['image'] ?? null,
                                        'created_at' => $setData['created_at'] ?? now(),
                                        'last_update' => $setData['last_update'] ?? now()
                                    ]
                                );

                                $edition = Edition::updateOrCreate(
                                    ['id' => $editionData['uuid']],
                                    [
                                        'card_id' => $cardData['uuid'],
                                        'set_id' => $set->id,
                                        'collector_number' => $editionData['collector_number'] ?? null,
                                        'configuration' => $editionData['configuration'] ?? null,
                                        'image' => $editionData['image'] ?? null,
                                        'rarity' => $editionData['rarity'] ?? null,
                                        'slug' => $editionData['slug'] ?? null,
                                        'orientation' => $editionData['orientation'] ?? null,
                                        'other_orientations' => $editionData['other_orientations'] ?? null,
                                        'effect' => $editionData['effect'] ?? null,
                                        'effect_html' => $editionData['effect_html'] ?? null,
                                        'effect_raw' => $editionData['effect_raw'] ?? null,
                                        'flavor' => $editionData['flavor'] ?? null,
                                        'illustrator' => $editionData['illustrator'] ?? null,
                                        'created_at' => $editionData['created_at'] ?? now(),
                                        'last_update' => $editionData['last_update'] ?? now()
                                    ]
                                );
                            }
                        }
                    }

                    $totalProcessed++;
                }

                $this->info("Processed page {$page}, total cards: {$totalProcessed}");
            }

            // Store summary instead of displaying immediately
            $this->summaries['cards'] = [
                'total_processed' => $totalProcessed,
                'cards_created' => $cardsCreated,
                'cards_updated' => $cardsUpdated,
                'cards_skipped' => $cardsSkipped,
                'errors' => $errors,
                'is_dry_run' => $isDryRun,
                'dry_run_changes' => $isDryRun ? $dryRunChanges : []
            ];
        } catch (\Exception $e) {
            $this->error("Error fetching cards: " . $e->getMessage());
            $this->error($e->getTraceAsString());
            // Store empty summary on error
            $this->summaries['cards'] = [
                'total_processed' => 0,
                'cards_created' => 0,
                'cards_updated' => 0,
                'cards_skipped' => 0,
                'errors' => 1,
                'is_dry_run' => $this->option('dry-run'),
                'dry_run_changes' => []
            ];
        }
    }

    private function fetchEditions()
    {
        $this->info('Fetching editions data from cards...');

        try {
            // If --all is used and no limit specified, process all cards (unlimited)
            $limit = $this->option('limit') ? (int) $this->option('limit') : ($this->option('all') ? PHP_INT_MAX : 50);
            $pageSize = min(50, $limit === PHP_INT_MAX ? 50 : $limit);
            $totalProcessed = 0;
            $editionsCreated = 0;
            $editionsUpdated = 0;
            $skipped = 0;
            $isDryRun = $this->option('dry-run');
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

                foreach ($cards as $cardData) {
                    if ($totalProcessed >= $limit) break;

                    $card = Card::find($cardData['uuid']);
                    if (!$card) {
                        $this->warn("Card {$cardData['name']} (UUID: {$cardData['uuid']}) not found in database, skipping");
                        $skipped++;
                        $totalProcessed++;
                        continue;
                    }

                    if (isset($cardData['editions']) && is_array($cardData['editions'])) {
                        foreach ($cardData['editions'] as $editionData) {
                            // Process flip card images for this specific edition (only if not dry run)
                            if (!$isDryRun) {
                                $this->processFlipCardImages($editionData);
                            }
                            
                            // Ensure set exists (create if it doesn't)
                            if (!isset($editionData['set'])) {
                                $this->warn("Edition missing set data, skipping");
                                continue;
                            }
                            
                            $setData = $editionData['set'];
                            
                            if ($isDryRun) {
                                $set = Set::find($setData['id']);
                                if (!$set) {
                                    // In dry-run, we can't create the set, but we can still report what would happen
                                    $prefix = $setData['prefix'] ?? 'N/A';
                                    $this->line("Would create set: {$setData['name']} ({$prefix})");
                                    // Create a temporary set object for dry-run reporting
                                    $set = (object)['id' => $setData['id'], 'name' => $setData['name']];
                                }
                            } else {
                                // Create or update the set
                                $set = Set::updateOrCreate(
                                    ['id' => $setData['id']],
                                    [
                                        'name' => $setData['name'],
                                        'prefix' => $setData['prefix'] ?? null,
                                        'language' => $setData['language'] ?? null,
                                        'release_date' => $setData['release_date'] ?? null,
                                        'image' => $setData['image'] ?? null,
                                        'created_at' => $setData['created_at'] ?? now(),
                                        'last_update' => $setData['last_update'] ?? now()
                                    ]
                                );
                            }

                            $updateData = [
                                'card_id' => $card->id,
                                'set_id' => $set->id,
                                'collector_number' => $editionData['collector_number'] ?? null,
                                'configuration' => $editionData['configuration'] ?? null,
                                'image' => $editionData['image'] ?? null,
                                'rarity' => $editionData['rarity'] ?? null,
                                'slug' => $editionData['slug'] ?? null,
                                'orientation' => $editionData['orientation'] ?? null,
                                'other_orientations' => $editionData['other_orientations'] ?? null,
                                'effect' => $editionData['effect'] ?? null,
                                'effect_html' => $editionData['effect_html'] ?? null,
                                'effect_raw' => $editionData['effect_raw'] ?? null,
                                'flavor' => $editionData['flavor'] ?? null,
                                'illustrator' => $editionData['illustrator'] ?? null,
                                'last_update' => now()
                            ];

                            if ($isDryRun) {
                                // Check if edition exists for dry-run reporting
                                $existingEdition = Edition::find($editionData['uuid']);
                                
                                if ($existingEdition) {
                                    $changes = $this->getEditionFieldChanges($existingEdition, $updateData);
                                    
                                    // Filter out changes that only affect last_update
                                    $meaningfulChanges = array_filter($changes, function($field) {
                                        return $field !== 'last_update';
                                    }, ARRAY_FILTER_USE_KEY);
                                    
                                    if (!empty($meaningfulChanges)) {
                                        $dryRunChanges[] = [
                                            'name' => $card->name,
                                            'edition_id' => $editionData['uuid'],
                                            'set_name' => $set->name,
                                            'collector_number' => $editionData['collector_number'] ?? null,
                                            'changes' => $meaningfulChanges
                                        ];
                                    }
                                } else {
                                    $dryRunChanges[] = [
                                        'name' => $card->name,
                                        'edition_id' => $editionData['uuid'],
                                        'set_name' => $set->name,
                                        'collector_number' => $editionData['collector_number'] ?? null,
                                        'action' => 'create'
                                    ];
                                }
                            } else {
                                // Check if edition exists first
                                $existingEdition = Edition::find($editionData['uuid']);
                                
                                if ($existingEdition) {
                                    // Update existing edition (don't touch created_at)
                                    $existingEdition->update($updateData);
                                    $editionsUpdated++;
                                } else {
                                    // Create new edition (include created_at)
                                    $edition = Edition::create(array_merge($updateData, [
                                        'id' => $editionData['uuid'],
                                        'created_at' => $editionData['created_at'] ?? now()
                                    ]));
                                    $editionsCreated++;
                                    $this->line("Created edition: {$card->name} - {$set->name} ({$edition->collector_number})");
                                }
                            }
                        }
                    }

                    $totalProcessed++;
                }

                $this->info("Processed page {$page}, total cards: {$totalProcessed}");
            }

            // Store summary instead of displaying immediately
            $this->summaries['editions'] = [
                'total_processed' => $totalProcessed,
                'editions_created' => $editionsCreated,
                'editions_updated' => $editionsUpdated,
                'skipped' => $skipped,
                'is_dry_run' => $isDryRun,
                'dry_run_changes' => $isDryRun ? $dryRunChanges : []
            ];
        } catch (\Exception $e) {
            $this->error("Error fetching editions: " . $e->getMessage());
            // Store empty summary on error
            $this->summaries['editions'] = [
                'total_processed' => 0,
                'editions_created' => 0,
                'editions_updated' => 0,
                'skipped' => 0,
                'is_dry_run' => $this->option('dry-run'),
                'dry_run_changes' => []
            ];
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

    private function getEditionFieldChanges($existingEdition, $updateData)
    {
        $changedFields = [];
        
        foreach ($updateData as $field => $newValue) {
            $currentValue = $existingEdition->$field;
            
            // Handle null comparisons
            if ($currentValue === null && $newValue === null) {
                continue;
            }
            
            if ($currentValue === null && $newValue !== null) {
                $changedFields[$field] = [
                    'current' => 'null',
                    'new' => $newValue
                ];
                continue;
            }
            
            if ($currentValue !== null && $newValue === null) {
                $changedFields[$field] = [
                    'current' => $currentValue,
                    'new' => 'null'
                ];
                continue;
            }
            
            // Handle JSON/array comparisons for fields like other_orientations
            if ($field === 'other_orientations' || $field === 'effect_html') {
                $currentJson = is_string($currentValue) ? $currentValue : json_encode($currentValue);
                $newJson = is_string($newValue) ? $newValue : json_encode($newValue);
                
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
                    'current' => $currentValue,
                    'new' => $newValue
                ];
            }
        }
        
        return $changedFields;
    }

    private function displayEditionDryRunSummary($dryRunChanges)
    {
        $this->info('');
        $this->info('📋 EDITION DRY RUN SUMMARY - Editions That Would Be Updated:');
        $this->info('=' . str_repeat('=', 60));
        
        $editionsWithChanges = 0;
        $editionsToCreate = 0;
        
        foreach ($dryRunChanges as $change) {
            if (isset($change['action']) && $change['action'] === 'create') {
                $editionsToCreate++;
                $this->info('');
                $this->info("🆕 WOULD CREATE: {$change['name']} - {$change['set_name']} ({$change['collector_number']})");
                $this->info("  • Edition ID: {$change['edition_id']}");
            } else {
                $editionsWithChanges++;
                $this->info('');
                $this->info("🔄 WOULD UPDATE: {$change['name']} - {$change['set_name']} ({$change['collector_number']})");
                $this->info("  • Edition ID: {$change['edition_id']}");
                
                foreach ($change['changes'] as $field => $values) {
                    $this->info("  • {$field}:");
                    $this->info("    Current: " . (is_string($values['current']) && strlen($values['current']) > 50 ? substr($values['current'], 0, 50) . '...' : $values['current']));
                    $this->info("    New: " . (is_string($values['new']) && strlen($values['new']) > 50 ? substr($values['new'], 0, 50) . '...' : $values['new']));
                }
            }
        }
        
        $this->info('');
        $this->info('📊 EDITION SUMMARY STATISTICS:');
        $this->info("  • Editions with changes: {$editionsWithChanges}");
        $this->info("  • Editions to create: {$editionsToCreate}");
        $this->info('');
        $this->info('=' . str_repeat('=', 60));
    }

    private function processFlipCardImages($editionData)
    {
        if (isset($editionData['other_orientations']) && is_array($editionData['other_orientations'])) {
            foreach ($editionData['other_orientations'] as $orientationData) {
                if (isset($orientationData['edition']) && 
                    isset($orientationData['edition']['image'])) {
                    
                    $flipImageUrl = $orientationData['edition']['image'];
                    $flipCardName = $orientationData['name'] ?? 'flip-card';
                    
                    try {
                        $this->downloadCardImage($flipImageUrl, $flipCardName);
                    } catch (\Exception $e) {
                        $this->warn("Failed to download flip card image {$flipImageUrl}: " . $e->getMessage());
                    }
                }
            }
        }
    }

    private function downloadCardImage($imageUrl, $cardName)
    {
        try {
            // Handle relative URLs by prepending the base URL
            if (strpos($imageUrl, 'http') !== 0) {
                $imageUrl = 'https://api.gatcg.com' . $imageUrl;
            }
            
            // Extract filename from URL
            $filename = basename(parse_url($imageUrl, PHP_URL_PATH));
            
            // Clean up the filename
            $filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);
            
            // Check if file already exists in Spaces bucket
            if (Storage::disk('spaces')->exists("cards/{$filename}")) {
                return;
            }
            
            // Get the image content
            $response = Http::timeout(30)->get($imageUrl);
            
            if (!$response->successful()) {
                throw new \Exception("Failed to download image: HTTP {$response->status()}");
            }
            
            // Store in Spaces bucket
            Storage::disk('spaces')->put("cards/{$filename}", $response->body());
            
            $this->line("Downloaded and stored: cards/{$filename}");
            
        } catch (\Exception $e) {
            $this->warn("Error downloading image {$imageUrl}: " . $e->getMessage());
            throw $e;
        }
    }

    private function displayFinalSummary()
    {
        $this->info('');
        $this->info('');
        $this->info('═══════════════════════════════════════════════════════════════');
        $this->info('📊 FINAL SUMMARY');
        $this->info('═══════════════════════════════════════════════════════════════');
        $this->info('');

        // Sets Summary
        if (isset($this->summaries['sets'])) {
            $summary = $this->summaries['sets'];
            $this->info('📦 SETS:');
            $this->info("  • Cards processed: {$summary['total_processed']}");
            $action = $summary['is_dry_run'] ? 'Would create' : 'Created';
            $this->info("  • {$action}: {$summary['sets_created']} new sets");
            $this->info('');
        } else {
            $this->warn('⚠️  Sets summary not available');
            $this->info('');
        }

        // Cards Summary
        if (isset($this->summaries['cards'])) {
            $summary = $this->summaries['cards'];
            $this->info('🃏 CARDS:');
            $this->info("  • Total processed: {$summary['total_processed']}");
            
            if ($summary['is_dry_run']) {
                // Count cards from dry_run_changes
                $cardsToCreate = 0;
                $cardsToUpdate = 0;
                if (!empty($summary['dry_run_changes'])) {
                    foreach ($summary['dry_run_changes'] as $change) {
                        if (isset($change['action'])) {
                            if ($change['action'] === 'create') {
                                $cardsToCreate++;
                            } elseif ($change['action'] === 'skip') {
                                // Skip is already counted in cards_skipped
                            }
                        } else {
                            // Has changes array means update
                            $cardsToUpdate++;
                        }
                    }
                }
                $this->info("  • Cards to create: {$cardsToCreate}");
                $this->info("  • Cards to update: {$cardsToUpdate}");
                $this->info("  • Cards to skip: {$summary['cards_skipped']}");
            } else {
                $this->info("  • Created: {$summary['cards_created']}");
                $this->info("  • Updated: {$summary['cards_updated']}");
                $this->info("  • Skipped: {$summary['cards_skipped']}");
                $this->info("  • Errors: {$summary['errors']}");
            }
            $this->info('');
        } else {
            $this->warn('⚠️  Cards summary not available');
            $this->info('');
        }

        // Editions Summary
        if (isset($this->summaries['editions'])) {
            $summary = $this->summaries['editions'];
            $this->info('📚 EDITIONS:');
            $this->info("  • Cards processed: {$summary['total_processed']}");
            
            if ($summary['is_dry_run']) {
                // Count editions from dry_run_changes
                $editionsToCreate = 0;
                $editionsWithChanges = 0;
                if (!empty($summary['dry_run_changes'])) {
                    foreach ($summary['dry_run_changes'] as $change) {
                        if (isset($change['action']) && $change['action'] === 'create') {
                            $editionsToCreate++;
                        } else {
                            $editionsWithChanges++;
                        }
                    }
                }
                $this->info("  • Editions with changes: {$editionsWithChanges}");
                $this->info("  • Editions to create: {$editionsToCreate}");
            } else {
                $this->info("  • Created: {$summary['editions_created']}");
                $this->info("  • Updated: {$summary['editions_updated']}");
                $this->info("  • Skipped: {$summary['skipped']}");
            }
            $this->info('');
        } else {
            $this->warn('⚠️  Editions summary not available');
            $this->info('');
        }

        $this->info('═══════════════════════════════════════════════════════════════');
    }
}

