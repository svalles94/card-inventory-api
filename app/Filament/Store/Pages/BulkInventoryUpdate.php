<?php

namespace App\Filament\Store\Pages;

use App\Models\Card;
use App\Models\Inventory;
use App\Models\Location;
use App\Models\Edition;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Storage;
use League\Csv\Reader;
use League\Csv\Writer;

class BulkInventoryUpdate extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-up-tray';
    protected static ?string $navigationLabel = 'Bulk Update';
    protected static ?string $navigationGroup = 'Inventory';
    protected static string $view = 'filament.store.pages.bulk-inventory-update';

    public ?array $data = [];
    public ?string $selectedGame = null;
    public ?int $selectedLocation = null;
    public ?string $importProgressKey = null;
    public bool $isImporting = false;

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('game')
                    ->label('Select Game')
                    ->options([
                        'grand-archive' => 'Grand Archive',
                        'gundam' => 'Gundam',
                        'riftbound' => 'Riftbound',
                    ])
                    ->required()
                    ->live()
                    ->afterStateUpdated(fn ($state) => $this->selectedGame = $state),

                Select::make('location')
                    ->label('Select Location')
                    ->options(fn () => Location::pluck('name', 'id'))
                    ->required()
                    ->live()
                    ->afterStateUpdated(fn ($state) => $this->selectedLocation = $state),

                FileUpload::make('csv_file')
                    ->label('Upload CSV')
                    ->acceptedFileTypes(['text/csv', 'application/csv'])
                    ->disk('local')
                    ->directory('temp-csv')
                    ->visibility('private')
                    ->helperText('CSV format: card_name, set_code, collector_number (optional), edition_slug (optional), is_foil (TRUE/FALSE), quantity, quantity_mode (add/replace), buy_price (optional), sell_price (optional), location_name (optional)'),
            ])
            ->statePath('data');
    }

    public function downloadTemplate(): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        // Get form state - try both $this->data and form state
        $formData = $this->form->getState();
        $game = $formData['game'] ?? $this->data['game'] ?? $this->selectedGame ?? null;
        $locationId = $formData['location'] ?? $this->data['location'] ?? $this->selectedLocation ?? null;

        if (!$game || !$locationId) {
            Notification::make()
                ->title('Please select both a game and location first')
                ->body("Game: " . ($game ?? 'not set') . ", Location: " . ($locationId ?? 'not set'))
                ->danger()
                ->send();
            return response()->stream(function () {}, 200);
        }

        $location = Location::find($locationId);
        
        if (!$location) {
            Notification::make()
                ->title('Location not found')
                ->danger()
                ->send();
            return response()->stream(function () {}, 200);
        }
        
        // Query cards - handle both explicit game values and null (defaults to grand-archive)
        if ($game === 'grand-archive') {
            // Include cards with 'grand-archive' or null (null defaults to grand-archive)
            $cards = Card::where(function($query) {
                $query->where('game', 'grand-archive')
                      ->orWhereNull('game');
            })->orderBy('name')->get();
        } else {
        $cards = Card::where('game', $game)->orderBy('name')->get();
        }
        
        // Debug: Log card count
        \Log::info("BulkInventoryUpdate: Found {$cards->count()} cards for game '{$game}', locationId: {$locationId}");
        
        if ($cards->isEmpty()) {
            // Check what games actually exist
            $totalCount = Card::count();
            $availableGames = Card::distinct()->whereNotNull('game')->pluck('game')->toArray();
            $nullCount = Card::whereNull('game')->count();
            $grandArchiveCount = Card::where('game', 'grand-archive')->count();
            $nullOrGrandArchiveCount = Card::where(function($q) {
                $q->where('game', 'grand-archive')->orWhereNull('game');
            })->count();
            
            $message = "No cards found for game '{$game}'.\n\n";
            $message .= "Total cards in database: {$totalCount}\n";
            $message .= "Cards with game='grand-archive': {$grandArchiveCount}\n";
            $message .= "Cards with null game: {$nullCount}\n";
            $message .= "Cards matching query (grand-archive or null): {$nullOrGrandArchiveCount}\n";
            
            if (!empty($availableGames)) {
                $message .= "Available game values: " . implode(', ', $availableGames) . "\n";
            }
            
            $message .= "\n💡 Solution: Run this command to fetch Grand Archive cards:\n";
            $message .= "./vendor/bin/sail artisan grand-archive:fetch --all";
            
            Notification::make()
                ->title('No cards found')
                ->body($message)
                ->warning()
                ->persistent()
                ->send();
            return response()->stream(function () {}, 200);
        }

        // Create CSV
        $csv = Writer::createFromString();
        
        // Add headers - New improved format
        $csv->insertOne([
            'card_name',
            'set_code',
            'collector_number',
            'edition_slug',
            'is_foil',
            'quantity',
            'quantity_mode',
            'buy_price',
            'sell_price',
            'location_name',
        ]);

        // Add card rows with all editions
        foreach ($cards as $card) {
            // Skip cards without name
            if (empty($card->name)) {
                continue;
            }
            
            // Get set_code from card, or try to get it from first edition's set
            $setCode = $card->set_code;
            if (empty($setCode)) {
                $firstEdition = $card->editions()->with('set')->first();
                if ($firstEdition && $firstEdition->set) {
                    // Set model uses 'prefix' field as the set code
                    // Uppercase to match import logic
                    $setCode = strtoupper(trim($firstEdition->set->prefix ?? ''));
                }
            }
            
            $editions = $card->editions()->orderByDesc('last_update')->get();
            
            // If no editions, create a row with just the card info
            if ($editions->isEmpty()) {
            $inventory = Inventory::where('card_id', $card->id)
                ->where('location_id', $locationId)
                    ->where('is_foil', false)
                    ->first();
                
                $csv->insertOne([
                    $card->name,
                    $setCode ?? '',
                    $card->card_number ?? '',
                    '', // No edition
                    'FALSE',
                    $inventory ? $inventory->quantity : '',
                    'replace', // Default mode
                    $inventory ? $inventory->buy_price : '',
                    $inventory ? $inventory->sell_price : '',
                    $location->name,
                ]);
            } else {
                // Add a row for each edition (foil and non-foil)
                foreach ($editions as $edition) {
                    // Use set_code from edition's set if card doesn't have it
                    $editionSetCode = $setCode;
                    if (empty($editionSetCode)) {
                        // Load set relationship if not already loaded
                        if (!$edition->relationLoaded('set')) {
                            $edition->load('set');
                        }
                        if ($edition->set) {
                            // Set model uses 'prefix' field as the set code
                            // Uppercase to match import logic
                            $editionSetCode = strtoupper(trim($edition->set->prefix ?? ''));
                        }
                    }
                    
                    // Non-foil row
                    $inventory = Inventory::where('card_id', $card->id)
                        ->where('edition_id', $edition->id)
                        ->where('location_id', $locationId)
                        ->where('is_foil', false)
                ->first();

            $csv->insertOne([
                $card->name,
                        $editionSetCode ?? '',
                        $edition->collector_number ?? $card->card_number ?? '',
                        $edition->slug ?? '',
                        'FALSE',
                        $inventory ? $inventory->quantity : '',
                        'replace',
                $inventory ? $inventory->buy_price : '',
                $inventory ? $inventory->sell_price : '',
                        $location->name,
                    ]);
                    
                    // Foil row
                    $foilInventory = Inventory::where('card_id', $card->id)
                        ->where('edition_id', $edition->id)
                        ->where('location_id', $locationId)
                        ->where('is_foil', true)
                        ->first();
                    
                    $csv->insertOne([
                        $card->name,
                        $editionSetCode ?? '',
                        $edition->collector_number ?? $card->card_number ?? '',
                        $edition->slug ?? '',
                        'TRUE',
                        $foilInventory ? $foilInventory->quantity : '',
                        'replace',
                        $foilInventory ? $foilInventory->buy_price : '',
                        $foilInventory ? $foilInventory->sell_price : '',
                        $location->name,
                    ]);
                }
            }
        }

        $filename = sprintf(
            '%s_inventory_%s_%s.csv',
            str_replace(' ', '_', strtolower($game)),
            str_replace(' ', '_', strtolower($location->name)),
            now()->format('Y-m-d')
        );

        return response()->streamDownload(
            fn () => print($csv->toString()),
            $filename,
            ['Content-Type' => 'text/csv']
        );
    }

    public function uploadCsv(): void
    {
        $data = $this->form->getState();
        
        if (empty($data['csv_file'])) {
            Notification::make()
                ->title('Please upload a CSV file')
                ->danger()
                ->send();
            return;
        }

        $locationId = $data['location'];
        
        if (!$locationId) {
            Notification::make()
                ->title('Please select a location')
                ->danger()
                ->send();
            return;
        }
        
        $filePath = Storage::disk('local')->path($data['csv_file']);

        if (!file_exists($filePath)) {
            Notification::make()
                ->title('CSV file not found')
                ->body("File path: {$filePath}")
                ->danger()
                ->send();
            return;
        }

        try {
            // Increase execution time and memory for large imports
            set_time_limit(300); // 5 minutes
            ini_set('memory_limit', '512M'); // Increase memory for large files
            
            $csv = Reader::createFromPath($filePath, 'r');
            $csv->setHeaderOffset(0);
            
            // Validate headers
            $headers = $csv->getHeader();
            
            if (empty($headers)) {
                Notification::make()
                    ->title('Invalid CSV file')
                    ->body('CSV file appears to be empty or has no headers.')
                    ->danger()
                    ->send();
                return;
            }
            
            $requiredHeaders = ['card_name', 'set_code', 'is_foil', 'quantity'];
            $headerMap = [];
            foreach ($headers as $index => $header) {
                $normalized = strtolower(trim($header));
                // Remove BOM if present
                $normalized = preg_replace('/^\xEF\xBB\xBF/', '', $normalized);
                $headerMap[$normalized] = $index;
            }
            
            $missingHeaders = [];
            foreach ($requiredHeaders as $required) {
                if (!isset($headerMap[$required])) {
                    $missingHeaders[] = $required;
                }
            }
            
            if (!empty($missingHeaders)) {
                Notification::make()
                    ->title('Invalid CSV format')
                    ->body("Missing required columns: " . implode(', ', $missingHeaders) . ". Found headers: " . implode(', ', array_keys($headerMap)))
                    ->danger()
                    ->send();
                return;
            }
            
            // Generate unique progress key
            $progressKey = 'csv_import_progress_' . Auth::id() . '_' . time();
            $this->importProgressKey = $progressKey;
            $this->isImporting = true;
            
            // Initialize progress
            Cache::put($progressKey, [
                'total' => 0,
                'processed' => 0,
                'created' => 0,
                'updated' => 0,
                'failed' => 0,
                'status' => 'starting',
                'message' => 'Reading CSV file...'
            ], now()->addMinutes(10));
            
            // Dispatch event to trigger view update
            $this->dispatch('import-started');
            
            // Show initial notification
            Notification::make()
                ->title('Import started')
                ->body('Processing CSV file... This may take a moment for large files.')
                ->info()
                ->send();
            
            $records = iterator_to_array($csv->getRecords());
            $totalRows = count($records);
            
            // Update total
            Cache::put($progressKey, [
                'total' => $totalRows,
                'processed' => 0,
                'created' => 0,
                'updated' => 0,
                'failed' => 0,
                'status' => 'processing',
                'message' => "Processing {$totalRows} rows..."
            ], now()->addMinutes(10));

            $updated = 0;
            $created = 0;
            $failed = 0;
            $errors = [];
            $user = Auth::user();
            $store = $user->currentStore();
            
            if (!$store) {
                Notification::make()
                    ->title('No store selected')
                    ->danger()
                    ->send();
                return;
            }
            
            \Log::info('CSV Import Started', [
                'file' => $filePath,
                'location_id' => $locationId,
                'store_id' => $store->id
            ]);

            $rowCount = 0;
            foreach ($records as $offset => $record) {
                $rowCount++;
                
                // Update progress every 10 rows (or every row for small files)
                if ($rowCount % 10 === 0 || $rowCount <= 10 || $rowCount === $totalRows) {
                    $percentage = $totalRows > 0 ? round(($rowCount / $totalRows) * 100, 1) : 0;
                    Cache::put($progressKey, [
                        'total' => $totalRows,
                        'processed' => $rowCount,
                        'created' => $created,
                        'updated' => $updated,
                        'failed' => $failed,
                        'status' => 'processing',
                        'message' => "Processed {$rowCount} of {$totalRows} rows ({$percentage}%)",
                        'percentage' => $percentage
                    ], now()->addMinutes(10));
                }
                
                // Log progress every 100 rows
                if ($rowCount % 100 === 0) {
                    \Log::info("CSV Import Progress: Processed {$rowCount} rows", [
                        'created' => $created,
                        'updated' => $updated,
                        'failed' => $failed
                    ]);
                }
                
                // Log first few rows in detail
                if ($rowCount <= 5) {
                    \Log::info("CSV Import: Processing row {$rowCount}", [
                        'offset' => $offset,
                        'record_keys' => array_keys($record),
                        'record_sample' => array_slice($record, 0, 5)
                    ]);
                }
                try {
                    // Convert record to array if it's not already
                    if (!is_array($record)) {
                        $record = iterator_to_array($record);
                    }
                    
                    // Skip completely empty rows
                    $hasData = false;
                    foreach ($record as $value) {
                        if (trim($value ?? '') !== '') {
                            $hasData = true;
                            break;
                        }
                    }
                    if (!$hasData) {
                        continue; // Skip empty rows silently
                    }
                    
                    // Helper function to get field value (case-insensitive, handles spaces/underscores)
                    $getField = function($key) use ($record) {
                        // Normalize the key: lowercase, trim, replace spaces/underscores
                        $normalize = function($str) {
                            return str_replace([' ', '_'], '', strtolower(trim($str)));
                        };
                        
                        $normalizedKey = $normalize($key);
                        
                        // Try exact match first
                        if (isset($record[$key])) {
                            return $record[$key];
                        }
                        
                        // Try case-insensitive match with normalized comparison
                        foreach ($record as $header => $value) {
                            $normalizedHeader = $normalize($header);
                            if ($normalizedHeader === $normalizedKey) {
                                return $value;
                            }
                        }
                        return null;
                    };
                    
                    // Parse required fields
                    $cardName = trim($getField('card_name') ?? '');
                    // Normalize set code: uppercase, trim, remove control characters
                    $rawSetCode = $getField('set_code') ?? '';
                    $setCode = strtoupper(trim(preg_replace('/[\x00-\x1F\x7F]/', '', $rawSetCode)));
                    $isFoil = filter_var($getField('is_foil') ?? false, FILTER_VALIDATE_BOOLEAN);
                    $quantity = !empty($getField('quantity')) ? (int) $getField('quantity') : null;
                    $quantityMode = strtolower(trim($getField('quantity_mode') ?? 'replace')); // 'add' or 'replace'
                    
                    // Optional fields
                    $collectorNumber = trim($getField('collector_number') ?? '');
                    $editionSlug = trim($getField('edition_slug') ?? '');
                    $buyPrice = !empty($getField('buy_price')) ? (float) $getField('buy_price') : null;
                    $sellPrice = !empty($getField('sell_price')) ? (float) $getField('sell_price') : null;
                    $locationName = trim($getField('location_name') ?? '');
                    
                    // Log parsed fields for first few rows
                    if ($rowCount <= 3) {
                        \Log::info('CSV Import: Parsed row fields', [
                            'row' => $offset + 2,
                            'card_name' => $cardName,
                            'raw_set_code' => $rawSetCode,
                            'normalized_set_code' => $setCode,
                            'set_code_length' => strlen($setCode),
                            'set_code_hex' => bin2hex($setCode),
                            'is_foil' => $isFoil,
                            'quantity' => $quantity,
                            'collector_number' => $collectorNumber,
                            'edition_slug' => $editionSlug,
                            'all_record_keys' => array_keys($record)
                        ]);
                    }

                    // Validation - only error if row has some data but missing required fields
                    if (empty($cardName) || empty($setCode)) {
                        // Show what data was found to help debug (use getField to find actual values)
                        $foundData = [];
                        foreach (['card_name', 'set_code', 'collector_number', 'edition_slug', 'quantity'] as $field) {
                            $val = trim($getField($field) ?? '');
                            if ($val !== '') {
                                $foundData[] = "{$field}='{$val}'";
                            }
                        }
                        // Also show what headers actually exist in the record
                        $actualHeaders = array_keys($record);
                        $foundStr = !empty($foundData) ? " (found: " . implode(', ', $foundData) . ")" : " (row appears empty)";
                        $headersStr = " (CSV headers: " . implode(', ', $actualHeaders) . ")";
                        $failed++;
                        $errors[] = "Row " . ($offset + 2) . ": Missing card_name or set_code{$foundStr}{$headersStr}";
                    continue;
                }

                    // Find card - try multiple strategies
                    $card = null;
                    $matchAttempts = [];
                    
                    // Strategy 1: Direct match on card.set_code
                    // Try with collector_number first, then without if not found
                    $cardQuery = Card::where('name', $cardName)
                        ->where('set_code', $setCode);
                    
                    if ($collectorNumber) {
                        $card = $cardQuery->where('card_number', $collectorNumber)->first();
                        // If not found with collector_number, try without it
                        if (!$card) {
                            $card = Card::where('name', $cardName)
                                ->where('set_code', $setCode)
                                ->first();
                        }
                    } else {
                        $card = $cardQuery->first();
                    }
                    $matchAttempts[] = [
                        'strategy' => 'Direct match on card.set_code',
                        'found' => $card !== null,
                        'query' => "name='{$cardName}' AND set_code='{$setCode}'" . ($collectorNumber ? " AND card_number='{$collectorNumber}'" : '')
                    ];
                    
                    // Strategy 2: If not found, try matching by name only and check editions' sets
                    // Don't filter by collector_number here - it might not match exactly
                    if (!$card) {
                        $candidates = Card::where('name', $cardName)->get();
                        $matchAttempts[] = [
                            'strategy' => 'Match by name and check edition sets',
                            'candidates_found' => $candidates->count(),
                            'set_code_looking_for' => $setCode
                        ];
                        
                        // Check if any candidate has an edition with matching set prefix
                        foreach ($candidates as $candidate) {
                            $editions = $candidate->editions()->with('set')->get();
                            $editionMatches = [];
                            
                            foreach ($editions as $edition) {
                                if ($edition->set && $edition->set->prefix) {
                                    // Get raw values
                                    $rawPrefix = (string)$edition->set->prefix;
                                    $rawSetCode = (string)$setCode;
                                    
                                    // Try multiple normalization and comparison methods
                                    // Method 1: Full normalization
                                    $editionPrefix1 = strtoupper(trim(preg_replace('/[\x00-\x1F\x7F]/', '', $rawPrefix)));
                                    $normalizedSetCode1 = strtoupper(trim(preg_replace('/[\x00-\x1F\x7F]/', '', $rawSetCode)));
                                    
                                    // Method 2: Simple uppercase and trim
                                    $editionPrefix2 = strtoupper(trim($rawPrefix));
                                    $normalizedSetCode2 = strtoupper(trim($rawSetCode));
                                    
                                    // Method 3: Loose comparison
                                    $match1 = ($editionPrefix1 === $normalizedSetCode1);
                                    $match2 = ($editionPrefix2 === $normalizedSetCode2);
                                    $match3 = (strcasecmp(trim($rawPrefix), trim($rawSetCode)) === 0);
                                    
                                    $editionMatches[] = [
                                        'edition_id' => $edition->id,
                                        'raw_prefix' => $rawPrefix,
                                        'raw_set_code' => $rawSetCode,
                                        'normalized_prefix' => $editionPrefix1,
                                        'normalized_set_code' => $normalizedSetCode1,
                                        'match_method1' => $match1,
                                        'match_method2' => $match2,
                                        'match_method3' => $match3,
                                        'any_match' => $match1 || $match2 || $match3
                                    ];
                                    
                                    $matches = $match1 || $match2 || $match3;
                                    
                                    // Log detailed comparison for first few rows
                                    if ($rowCount <= 3) {
                                        \Log::info('CSV Import: Comparing set codes', [
                                            'row' => $offset + 2,
                                            'card_name' => $cardName,
                                            'candidate_card_id' => $candidate->id,
                                            'edition_id' => $edition->id,
                                            'raw_prefix' => $rawPrefix,
                                            'raw_prefix_length' => strlen($rawPrefix),
                                            'raw_prefix_hex' => bin2hex($rawPrefix),
                                            'raw_set_code' => $rawSetCode,
                                            'raw_set_code_length' => strlen($rawSetCode),
                                            'raw_set_code_hex' => bin2hex($rawSetCode),
                                            'normalized_prefix' => $editionPrefix1,
                                            'normalized_set_code' => $normalizedSetCode1,
                                            'match_method1' => $match1,
                                            'match_method2' => $match2,
                                            'match_method3' => $match3,
                                            'any_match' => $matches
                                        ]);
                                    }
                                    
                                    if ($matches) {
                                        $card = $candidate;
                                        $matchAttempts[] = [
                                            'strategy' => 'Edition set prefix match',
                                            'matched' => true,
                                            'card_id' => $card->id,
                                            'edition_id' => $edition->id,
                                            'prefix' => $rawPrefix,
                                            'set_code' => $rawSetCode
                                        ];
                                        \Log::info('CSV Import: Card matched via edition set prefix', [
                                            'row' => $offset + 2,
                                            'card_name' => $cardName,
                                            'card_id' => $card->id,
                                            'set_code' => $setCode,
                                            'edition_id' => $edition->id
                                        ]);
                                        break 2;
                                    }
                                }
                            }
                            
                            // Log edition matches for debugging if no match found
                            if (!$card && $rowCount <= 3) {
                                $matchAttempts[] = [
                                    'candidate_card_id' => $candidate->id,
                                    'edition_matches' => $editionMatches
                                ];
                            }
                        }
                        
                        // Log Strategy 2 results
                        if ($rowCount <= 3 && !$card) {
                            \Log::info('CSV Import: Strategy 2 completed - no match', [
                                'row' => $offset + 2,
                                'card_name' => $cardName,
                                'candidates_checked' => $candidates->count(),
                                'match_attempts' => $matchAttempts
                            ]);
                        }
                    }
                    
                    // Strategy 3: If edition_slug is provided, use that to narrow down
                    // Don't filter by collector_number - just match by name
                    if (!$card && $editionSlug) {
                        $candidates = Card::where('name', $cardName)->get();
                        
                        foreach ($candidates as $candidate) {
                            $edition = $candidate->editions()
                                ->where('slug', $editionSlug)
                                ->with('set')
                                ->first();
                            
                            if ($edition && $edition->set && $edition->set->prefix) {
                                // Normalize both values: uppercase, trim, remove any hidden characters
                                $editionPrefix = strtoupper(trim(preg_replace('/[\x00-\x1F\x7F]/', '', $edition->set->prefix)));
                                $normalizedSetCode = strtoupper(trim(preg_replace('/[\x00-\x1F\x7F]/', '', $setCode)));
                                
                                if ($editionPrefix === $normalizedSetCode) {
                                    $card = $candidate;
                                    break;
                                }
                            }
                        }
                    }
                    
                    if (!$card) {
                        // Debug: Try to find what cards exist with this name
                        $similarCards = Card::where('name', $cardName)->limit(5)->get();
                        $debugInfo = [];
                        foreach ($similarCards as $similar) {
                            $editions = $similar->editions()->with('set')->get();
                            $setPrefixes = [];
                            foreach ($editions as $ed) {
                                if ($ed->set && $ed->set->prefix) {
                                    $setPrefixes[] = strtoupper(trim($ed->set->prefix));
                                }
                            }
                            $setPrefixesStr = !empty($setPrefixes) ? implode(', ', array_unique($setPrefixes)) : 'none';
                            $debugInfo[] = "Card '{$similar->name}' has set prefixes: [{$setPrefixesStr}], looking for: [{$setCode}]";
                        }
                        $debugStr = !empty($debugInfo) ? implode('; ', $debugInfo) : "No cards found with name '{$cardName}'";
                        $failed++;
                        $errors[] = "Row " . ($offset + 2) . ": Card not found: {$cardName} ({$setCode}). {$debugStr}";
                        continue;
                    }

                    // Find edition if specified
                    $editionId = null;
                    if ($editionSlug) {
                        $edition = $card->editions()
                            ->where('slug', $editionSlug)
                            ->first();
                        if ($edition) {
                            $editionId = $edition->id;
                        }
                    } else {
                        // Default to latest edition
                        $latestEdition = $card->editions()
                            ->orderByDesc('last_update')
                            ->first();
                        if ($latestEdition) {
                            $editionId = $latestEdition->id;
                        }
                    }

                    // Determine location
                    $targetLocationId = $locationId; // Default to selected location
                    if ($locationName) {
                        $targetLocation = $store->locations()
                            ->where('name', $locationName)
                            ->first();
                        if ($targetLocation) {
                            $targetLocationId = $targetLocation->id;
                        } else {
                            $failed++;
                            $errors[] = "Row " . ($offset + 2) . ": Location not found: {$locationName}";
                            continue;
                        }
                    }

                    // Skip if quantity is empty (allows price-only updates)
                    if ($quantity === null) {
                        // Only update prices if provided
                        if ($buyPrice !== null || $sellPrice !== null) {
                            $existingInventory = Inventory::where('card_id', $card->id)
                                ->where('location_id', $targetLocationId)
                                ->where('is_foil', $isFoil)
                                ->when($editionId, fn($q) => $q->where('edition_id', $editionId))
                                ->first();
                            
                            if ($existingInventory) {
                                if ($buyPrice !== null) $existingInventory->buy_price = $buyPrice;
                                if ($sellPrice !== null) $existingInventory->sell_price = $sellPrice;
                                $existingInventory->save();
                                $updated++;
                            }
                        }
                        continue;
                    }

                    // Find or create inventory
                    $inventory = Inventory::firstOrNew([
                        'card_id' => $card->id,
                        'location_id' => $targetLocationId,
                        'edition_id' => $editionId,
                        'is_foil' => $isFoil,
                    ]);

                    // Handle quantity mode
                    if ($quantityMode === 'add') {
                        $inventory->quantity = ($inventory->quantity ?? 0) + $quantity;
                    } else {
                        // 'replace' mode
                        $inventory->quantity = $quantity;
                    }

                    // Update prices if provided
                    if ($buyPrice !== null) {
                        $inventory->buy_price = $buyPrice;
                    }
                    if ($sellPrice !== null) {
                        $inventory->sell_price = $sellPrice;
                    }

                    $wasRecentlyCreated = !$inventory->exists;
                    $inventory->save();

                    if ($wasRecentlyCreated) {
                        $created++;
                        if ($rowCount <= 5) {
                            \Log::info('CSV Import: Inventory created', [
                                'row' => $offset + 2,
                                'card_id' => $card->id,
                                'card_name' => $cardName,
                                'location_id' => $targetLocationId,
                                'edition_id' => $editionId,
                                'is_foil' => $isFoil,
                                'quantity' => $inventory->quantity
                            ]);
                        }
                    } else {
                        $updated++;
                        if ($rowCount <= 5) {
                            \Log::info('CSV Import: Inventory updated', [
                                'row' => $offset + 2,
                                'card_id' => $card->id,
                                'card_name' => $cardName,
                                'location_id' => $targetLocationId,
                                'quantity' => $inventory->quantity
                            ]);
                        }
                    }
                } catch (\Exception $e) {
                    $failed++;
                    $errors[] = "Row " . ($offset + 2) . ": {$e->getMessage()}";
                    \Log::warning('CSV Import Row Error', [
                        'row' => $offset + 2,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                }
            }

            // Update final progress
            Cache::put($progressKey, [
                'total' => $totalRows,
                'processed' => $rowCount,
                'created' => $created,
                'updated' => $updated,
                'failed' => $failed,
                'status' => 'completed',
                'message' => "Import completed! Created: {$created}, Updated: {$updated}, Failed: {$failed}",
                'percentage' => 100
            ], now()->addMinutes(10));

            // Clean up the uploaded file
            Storage::disk('local')->delete($data['csv_file']);

            // Mark import as complete
            $this->isImporting = false;

            if (empty($errors)) {
                Notification::make()
                    ->title('Import successful!')
                    ->body("Created {$created} new inventory records and updated {$updated} existing records.")
                    ->success()
                    ->send();
            } else {
                // Group errors by type to identify patterns
                $errorCounts = [];
                $sampleErrors = [];
                foreach ($errors as $error) {
                    // Extract error type (skip "Row X:" part, get actual error type)
                    // Format: "Row X: Error message here"
                    $parts = explode(':', $error, 3);
                    if (count($parts) >= 3) {
                        // Skip "Row X" and get the actual error type
                        $errorType = trim($parts[1]);
                    } elseif (count($parts) >= 2) {
                        $errorType = trim($parts[1]);
                    } else {
                        $errorType = 'Unknown';
                    }
                    
                    // Simplify error types for grouping
                    if (str_contains($errorType, 'Card not found')) {
                        $errorType = 'Card not found';
                    } elseif (str_contains($errorType, 'Missing card_name')) {
                        $errorType = 'Missing card_name or set_code';
                    } elseif (str_contains($errorType, 'Location not found')) {
                        $errorType = 'Location not found';
                    }
                    
                    $errorCounts[$errorType] = ($errorCounts[$errorType] ?? 0) + 1;
                    if (count($sampleErrors) < 10) {
                        $sampleErrors[] = $error;
                    }
                }
                
                $errorSummary = [];
                foreach ($errorCounts as $type => $count) {
                    $errorSummary[] = "{$type}: {$count}";
                }
                
                $body = "Created {$created}, updated {$updated}. Failed: {$failed} rows.\n\n";
                $body .= "Error breakdown: " . implode(', ', $errorSummary) . "\n\n";
                $body .= "Sample errors (first 5):\n" . implode("\n", array_slice($sampleErrors, 0, 5));
                
                Notification::make()
                    ->title('Import completed with errors')
                    ->body($body)
                    ->warning()
                    ->persistent()
                    ->send();
            }

            // Reset the form
            $this->form->fill();

        } catch (\Exception $e) {
            // Update progress with error
            if ($this->importProgressKey) {
                Cache::put($this->importProgressKey, [
                    'total' => 0,
                    'processed' => 0,
                    'created' => 0,
                    'updated' => 0,
                    'failed' => 0,
                    'status' => 'error',
                    'message' => 'Import failed: ' . $e->getMessage(),
                    'percentage' => 0
                ], now()->addMinutes(10));
            }
            
            $this->isImporting = false;
            
            \Log::error('CSV Import Error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'file' => $filePath ?? 'unknown'
            ]);
            
            Notification::make()
                ->title('Import failed')
                ->body("Error: {$e->getMessage()}. Check logs for details.")
                ->danger()
                ->send();
        }
    }
    
    public function getImportProgress()
    {
        if (!$this->importProgressKey) {
            $this->isImporting = false;
            return null;
        }
        
        $progress = Cache::get($this->importProgressKey);
        
        if (!$progress) {
            $this->isImporting = false;
            $this->importProgressKey = null;
            return null;
        }
        
        if (in_array($progress['status'], ['completed', 'error'])) {
            // Clear progress after completion/error
            Cache::forget($this->importProgressKey);
            $this->isImporting = false;
            $this->importProgressKey = null;
        }
        
        return $progress;
    }
    
    public function getProgressProperty()
    {
        return $this->getImportProgress();
    }
}
