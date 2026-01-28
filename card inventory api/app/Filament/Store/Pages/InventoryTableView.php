<?php

namespace App\Filament\Store\Pages;

use App\Models\Card;
use App\Models\Inventory;
use App\Models\Location;
use Filament\Tables\Actions\Action as TableAction;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InventoryTableView extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-table-cells';

    protected static ?string $navigationGroup = null;

    protected static ?string $navigationLabel = 'Bulk Updates';

    protected static ?int $navigationSort = 0;

    protected static bool $shouldRegisterNavigation = false;

    protected static string $view = 'filament.store.pages.inventory-table-view';

    public function table(Table $table): Table
    {
        $store = Auth::user()?->currentStore();

        return $table
            ->query(function (): Builder {
                $store = Auth::user()?->currentStore();

                if (! $store) {
                    return Inventory::query()->whereRaw('1 = 0');
                }

                return Inventory::query()
                    ->whereHas('location', fn (Builder $q) => $q->where('store_id', $store->id))
                    ->with(['location', 'card']);
            })
            ->columns([
                Tables\Columns\ImageColumn::make('card.image')
                    ->label('Image')
                    ->size(60)
                    ->toggleable()
                    ->visibleFrom('md')
                    ->defaultImageUrl('/images/card-placeholder.png'),
                Tables\Columns\TextColumn::make('card.name')
                    ->label('Card')
                    ->searchable()
                    ->sortable()
                    ->wrap(),
                Tables\Columns\TextColumn::make('card.set_code')
                    ->label('Set')
                    ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('card.card_number')
                    ->label('#')
                    ->sortable(),
                Tables\Columns\TextColumn::make('location.name')
                    ->label('Location')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\IconColumn::make('is_foil')
                    ->label('Foil')
                    ->boolean(),
                Tables\Columns\TextColumn::make('quantity')
                    ->label('Qty')
                    ->sortable()
                    ->badge()
                    ->color(fn ($state) => match (true) {
                        $state === 0 => 'danger',
                        $state < 4 => 'warning',
                        default => 'success',
                    }),
                Tables\Columns\TextColumn::make('sell_price')
                    ->label('Your Price')
                    ->money('USD')
                    ->sortable(),
                Tables\Columns\TextColumn::make('market_price')
                    ->label('TCGPlayer Market')
                    ->money('USD')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Updated')
                    ->since()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('location')
                    ->relationship('location', 'name', function ($query) use ($store) {
                        if (! $store) {
                            return $query;
                        }
                        return $query->where('store_id', $store->id);
                    })
                    ->label('Location')
                    ->searchable()
                    ->preload(),
                Tables\Filters\TernaryFilter::make('is_foil')
                    ->label('Foil'),
                Tables\Filters\Filter::make('in_stock')
                    ->label('In Stock')
                    ->query(fn (Builder $q) => $q->where('quantity', '>', 0)),
            ])
            ->headerActions([
                TableAction::make('download_template')
                    ->label('Download CSV Template')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->action(function () {
                        $user = Auth::user();
                        $store = $user->currentStore();
                        $currentLocation = $user->currentLocation();

                        if (!$store) {
                            Notification::make()->danger()->title('No store selected')->send();
                            return;
                        }

                        $headers = [
                            'Content-Type' => 'text/csv',
                            'Content-Disposition' => 'attachment; filename="inventory-template-' . now()->format('Y-m-d') . '.csv"',
                        ];

                        $callback = function () use ($store, $currentLocation) {
                            $output = fopen('php://output', 'w');
                            
                            // Headers
                            fputcsv($output, [
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

                            // Get all cards
                            $cards = Card::orderBy('game')->orderBy('name')->get();
                            $locationId = $currentLocation?->id;

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
                                        $setCode = $firstEdition->set->prefix ?? '';
                                    }
                                }
                                
                                $editions = $card->editions()->with('set')->orderByDesc('last_update')->get();
                                
                                // If no editions, create a row with just the card info
                                if ($editions->isEmpty()) {
                                    $inventory = null;
                                    if ($locationId) {
                                        $inventory = Inventory::where('card_id', $card->id)
                                            ->where('location_id', $locationId)
                                            ->where('is_foil', false)
                                            ->first();
                                    }
                                    
                                    fputcsv($output, [
                                        $card->name,
                                        $setCode ?? '',
                                        $card->card_number ?? '',
                                        '', // No edition
                                        'FALSE',
                                        $inventory ? $inventory->quantity : '',
                                        'replace',
                                        $inventory ? $inventory->buy_price : '',
                                        $inventory ? $inventory->sell_price : '',
                                        $currentLocation?->name ?? '',
                                    ]);
                                } else {
                                    // Add a row for each edition (foil and non-foil)
                                    foreach ($editions as $edition) {
                                        // Use set_code from edition's set if card doesn't have it
                                        $editionSetCode = $setCode;
                                        if (empty($editionSetCode) && $edition->set) {
                                            // Set model uses 'prefix' field as the set code
                                            $editionSetCode = $edition->set->prefix ?? '';
                                        }
                                        
                                        // Non-foil row
                                        $inventory = null;
                                        if ($locationId) {
                                            $inventory = Inventory::where('card_id', $card->id)
                                                ->where('edition_id', $edition->id)
                                                ->where('location_id', $locationId)
                                                ->where('is_foil', false)
                                                ->first();
                                        }
                                        
                                        fputcsv($output, [
                                            $card->name,
                                            $editionSetCode ?? '',
                                            $edition->collector_number ?? $card->card_number ?? '',
                                            $edition->slug ?? '',
                                            'FALSE',
                                            $inventory ? $inventory->quantity : '',
                                            'replace',
                                            $inventory ? $inventory->buy_price : '',
                                            $inventory ? $inventory->sell_price : '',
                                            $currentLocation?->name ?? '',
                                        ]);
                                        
                                        // Foil row
                                        $foilInventory = null;
                                        if ($locationId) {
                                            $foilInventory = Inventory::where('card_id', $card->id)
                                                ->where('edition_id', $edition->id)
                                                ->where('location_id', $locationId)
                                                ->where('is_foil', true)
                                                ->first();
                                        }
                                        
                                        fputcsv($output, [
                                            $card->name,
                                            $editionSetCode ?? '',
                                            $edition->collector_number ?? $card->card_number ?? '',
                                            $edition->slug ?? '',
                                            'TRUE',
                                            $foilInventory ? $foilInventory->quantity : '',
                                            'replace',
                                            $foilInventory ? $foilInventory->buy_price : '',
                                            $foilInventory ? $foilInventory->sell_price : '',
                                            $currentLocation?->name ?? '',
                                        ]);
                                    }
                                }
                            }

                            fclose($output);
                        };

                        return new StreamedResponse($callback, 200, $headers);
                    }),
                TableAction::make('upload_csv')
                    ->label('Upload CSV')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->modalWidth('lg')
                    ->form([
                        \Filament\Forms\Components\FileUpload::make('csv')
                            ->label('CSV File')
                            ->acceptedFileTypes(['text/csv', 'text/plain', 'application/csv'])
                            ->disk('local')
                            ->directory('tmp')
                            ->visibility('private')
                            ->required()
                            ->helperText('CSV format: card_name, set_code, collector_number (optional), edition_slug (optional), is_foil (TRUE/FALSE), quantity, quantity_mode (add/replace), buy_price (optional), sell_price (optional), location_name (optional)')
                            ->maxSize(1024),
                    ])
                    ->action(function (array $data) {
                        $user = Auth::user();
                        $store = $user->currentStore();

                        if (! $store) {
                            Notification::make()->danger()->title('No store selected')->send();
                            return;
                        }

                        $filePath = $data['csv'];
                        
                        // Handle Filament file upload path - try multiple locations
                        $fullPath = null;
                        if (str_starts_with($filePath, 'tmp/') || str_starts_with($filePath, 'temp-csv/')) {
                            $fullPath = Storage::disk('local')->path($filePath);
                        } else {
                            // Try local disk first
                            $fullPath = Storage::disk('local')->path($filePath);
                            if (!file_exists($fullPath)) {
                                // Fallback to default storage
                        $fullPath = Storage::path($filePath);
                            }
                        }

                        if (! file_exists($fullPath)) {
                            Notification::make()->danger()->title('File not found')->body("Could not locate uploaded file. Please try again.")->send();
                            return;
                        }

                        $handle = fopen($fullPath, 'r');
                        if (! $handle) {
                            Notification::make()->danger()->title('Unable to read file')->body("File exists but cannot be opened. Check file permissions.")->send();
                            return;
                        }

                        $header = fgetcsv($handle);
                        
                        if (!$header || empty($header)) {
                            fclose($handle);
                            Notification::make()->danger()->title('Invalid CSV file')->body('Could not read CSV headers. Make sure the file is a valid CSV.')->send();
                            return;
                        }
                        
                        // Clean headers: trim whitespace, remove BOM, lowercase for comparison
                        $header = array_map(function($col) {
                            // Remove BOM if present
                            $col = preg_replace('/^\xEF\xBB\xBF/', '', $col);
                            return trim($col);
                        }, $header);
                        
                        // Check if first row looks like a filename instead of headers
                        $firstHeader = strtolower(trim($header[0] ?? ''));
                        if (str_contains($firstHeader, '.csv') || 
                            str_contains($firstHeader, 'template') && count($header) === 1 ||
                            preg_match('/\d{4}-\d{2}-\d{2}/', $firstHeader) && count($header) === 1) {
                            fclose($handle);
                            Notification::make()
                                ->danger()
                                ->title('Invalid CSV Format')
                                ->body("The CSV file appears to have the filename in the first row instead of column headers.\n\nPlease make sure:\n1. The first row contains column names: card_name, set_code, is_foil, quantity\n2. You downloaded the template from the app (not just renamed a file)\n3. The file is saved as a proper CSV (not .txt renamed to .csv)\n\nTry downloading a fresh template from the 'Download CSV Template' button.")
                                ->send();
                            return;
                        }
                        
                        $required = ['card_name', 'set_code', 'is_foil', 'quantity'];
                        $headerLower = array_map('strtolower', $header);
                        $requiredLower = array_map('strtolower', $required);
                        
                        $missing = array_diff($requiredLower, $headerLower);
                        
                        if (!empty($missing)) {
                            fclose($handle);
                            $foundHeaders = implode(', ', $header);
                            $missingHeaders = implode(', ', array_map('ucfirst', array_map('str_replace', ['_'], [' '], $missing)));
                            Notification::make()
                                ->danger()
                                ->title('CSV header missing required columns')
                                ->body("Missing columns: {$missingHeaders}\n\nFound in file: {$foundHeaders}\n\nRequired columns: card_name, set_code, is_foil, quantity\n\nTip: Download a fresh template using the 'Download CSV Template' button to ensure correct format.")
                                ->send();
                            return;
                        }
                        
                        // Create a mapping from lowercase headers to actual headers for case-insensitive access
                        $headerMap = array_combine($headerLower, $header);

                        $success = 0;
                        $failed = 0;
                        $errorReasons = [
                            'empty_card_name' => 0,
                            'card_not_found' => 0,
                            'location_not_found' => 0,
                            'empty_quantity' => 0,
                        ];
                        $rowNumber = 1; // Start at 1 (header is row 0)
                        $sampleErrors = []; // Store first 5 errors for debugging

                        while (($row = fgetcsv($handle)) !== false) {
                            $rowNumber++;
                            
                            // Skip completely empty rows
                            if (empty($row) || (count($row) === 1 && trim($row[0]) === '')) {
                                continue;
                            }

                            // Skip rows that are all empty
                            $allEmpty = true;
                            foreach ($row as $cell) {
                                if (trim($cell) !== '') {
                                    $allEmpty = false;
                                    break;
                                }
                            }
                            if ($allEmpty) {
                                continue;
                            }

                            // Map row to headers (handle case-insensitive)
                            $record = [];
                            foreach ($header as $index => $headerName) {
                                $value = $row[$index] ?? '';
                                $record[strtolower($headerName)] = trim($value);
                            }

                            // Parse required fields (case-insensitive)
                            $cardName = trim($record['card_name'] ?? '');
                            $setCode = strtoupper(trim($record['set_code'] ?? ''));
                            $isFoil = filter_var($record['is_foil'] ?? false, FILTER_VALIDATE_BOOLEAN);
                            $quantity = !empty($record['quantity']) ? (int) $record['quantity'] : null;
                            $quantityMode = strtolower(trim($record['quantity_mode'] ?? 'add')); // 'add' or 'replace'
                            
                            // Optional fields
                            $collectorNumber = trim($record['collector_number'] ?? '');
                            $editionSlug = trim($record['edition_slug'] ?? '');
                            $buyPrice = !empty($record['buy_price']) ? (float) $record['buy_price'] : null;
                            $sellPrice = !empty($record['sell_price']) ? (float) $record['sell_price'] : null;
                            $locationName = trim($record['location_name'] ?? '');

                            // Skip rows with missing required data (but don't count as failures if truly empty)
                            if (! $cardName || ! $setCode) {
                                // Only count as failure if row has some data (not completely empty)
                                $hasAnyData = false;
                                foreach ($record as $value) {
                                    if (trim($value) !== '') {
                                        $hasAnyData = true;
                                        break;
                                    }
                                }
                                
                                if ($hasAnyData) {
                                $failed++;
                                    $errorReasons['empty_card_name']++;
                                    if (count($sampleErrors) < 5) {
                                        $sampleErrors[] = "Row {$rowNumber}: Missing card_name or set_code (found: " . implode(', ', array_filter($record)) . ")";
                                    }
                                }
                                // If row is completely empty, just skip it silently
                                continue;
                            }

                            // Find card - try multiple strategies
                            $card = null;
                            
                            // Strategy 1: Direct match on card.set_code
                            $cardQuery = Card::where('name', $cardName)
                                ->where('set_code', $setCode);
                            
                            if ($collectorNumber) {
                                $cardQuery->where('card_number', $collectorNumber);
                            }
                            
                            $card = $cardQuery->first();
                            
                            // Strategy 2: If not found, try case-insensitive name match with set_code
                            if (! $card) {
                                $cardQuery = Card::whereRaw('LOWER(name) = ?', [strtolower($cardName)])
                                    ->where('set_code', $setCode);
                                
                                if ($collectorNumber) {
                                    $cardQuery->where('card_number', $collectorNumber);
                                }
                                
                                $card = $cardQuery->first();
                            }
                            
                            // Strategy 3: If not found and card doesn't have set_code, check editions' sets
                            if (! $card) {
                                $cardQuery = Card::where('name', $cardName)
                                    ->where(function($q) {
                                        $q->whereNull('set_code')
                                          ->orWhere('set_code', '');
                                    });
                                
                                if ($collectorNumber) {
                                    $cardQuery->where('card_number', $collectorNumber);
                                }
                                
                                $candidates = $cardQuery->get();
                                
                                // Check if any candidate has an edition with matching set prefix
                                foreach ($candidates as $candidate) {
                                    $editions = $candidate->editions()->with('set')->get();
                                    foreach ($editions as $edition) {
                                        if ($edition->set && strtoupper($edition->set->prefix ?? '') === $setCode) {
                                            $card = $candidate;
                                            break 2;
                                        }
                                    }
                                }
                            }
                            
                            // Strategy 4: If edition_slug is provided, use that to narrow down
                            if (! $card && $editionSlug) {
                                $cardQuery = Card::where('name', $cardName);
                                
                                if ($collectorNumber) {
                                    $cardQuery->where('card_number', $collectorNumber);
                                }
                                
                                $candidates = $cardQuery->get();
                                
                                foreach ($candidates as $candidate) {
                                    $edition = $candidate->editions()
                                        ->where('slug', $editionSlug)
                                        ->with('set')
                                ->first();
                                    
                                    if ($edition && $edition->set && strtoupper($edition->set->prefix ?? '') === $setCode) {
                                        $card = $candidate;
                                        break;
                                    }
                                }
                            }

                            if (! $card) {
                                $failed++;
                                $errorReasons['card_not_found']++;
                                if (count($sampleErrors) < 5) {
                                    $sampleErrors[] = "Row {$rowNumber}: Card '{$cardName}' (Set: {$setCode}) not found in database";
                                }
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
                            $location = $user->currentLocation();
                            if ($locationName !== '') {
                                $location = $store->locations()->where('name', $locationName)->first();
                            }

                            if (! $location || $location->store_id !== $store->id) {
                                $failed++;
                                $errorReasons['location_not_found']++;
                                if (count($sampleErrors) < 5) {
                                    $locationHint = $locationName ? "Location '{$locationName}'" : "No location specified";
                                    $currentLocationName = $user->currentLocation()?->name ?? 'None';
                                    $sampleErrors[] = "Row {$rowNumber}: {$locationHint} not found. Current location: '{$currentLocationName}'";
                                }
                                continue;
                            }

                            // Skip if quantity is empty (allows price-only updates)
                            if ($quantity === null) {
                                // Only fail if no prices to update either
                                if ($buyPrice === null && $sellPrice === null) {
                                    $failed++;
                                    $errorReasons['empty_quantity']++;
                                    continue;
                                }
                                // Only update prices if provided
                                if ($buyPrice !== null || $sellPrice !== null) {
                                    $existingInventory = Inventory::where('card_id', $card->id)
                                        ->where('location_id', $location->id)
                                        ->where('is_foil', $isFoil)
                                        ->when($editionId, fn($q) => $q->where('edition_id', $editionId))
                                        ->first();
                                    
                                    if ($existingInventory) {
                                        if ($buyPrice !== null) $existingInventory->buy_price = $buyPrice;
                                        if ($sellPrice !== null) $existingInventory->sell_price = $sellPrice;
                                        $existingInventory->save();
                                        $success++;
                                    }
                                }
                                continue;
                            }

                            // Find or create inventory
                            $inventory = Inventory::firstOrNew([
                                'card_id' => $card->id,
                                'location_id' => $location->id,
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

                            $inventory->save();
                            $success++;
                        }

                        fclose($handle);
                        
                        // Clean up uploaded file
                        try {
                            if (str_starts_with($filePath, 'tmp/') || str_starts_with($filePath, 'temp-csv/')) {
                                Storage::disk('local')->delete($filePath);
                            } else {
                        Storage::delete($filePath);
                            }
                        } catch (\Exception $e) {
                            // Ignore cleanup errors
                        }

                        // Build detailed error message
                        $errorDetails = [];
                        if ($errorReasons['card_not_found'] > 0) {
                            $errorDetails[] = "{$errorReasons['card_not_found']} cards not found in database";
                        }
                        if ($errorReasons['location_not_found'] > 0) {
                            $errorDetails[] = "{$errorReasons['location_not_found']} location mismatches";
                        }
                        if ($errorReasons['empty_card_name'] > 0) {
                            $errorDetails[] = "{$errorReasons['empty_card_name']} rows with missing card name/set";
                        }
                        if ($errorReasons['empty_quantity'] > 0) {
                            $errorDetails[] = "{$errorReasons['empty_quantity']} rows with empty quantity (and no prices)";
                        }
                        
                        $message = "{$success} rows added/updated";
                        if ($failed > 0) {
                            $message .= ", {$failed} failed";
                            if (!empty($errorDetails)) {
                                $message .= "\n\n" . implode("\n", $errorDetails);
                            }
                            
                            // Add sample errors for debugging
                            if (!empty($sampleErrors)) {
                                $message .= "\n\nSample errors:\n" . implode("\n", array_slice($sampleErrors, 0, 5));
                            }
                            
                            // Add helpful tips based on most common error
                            $mostCommonError = array_search(max($errorReasons), $errorReasons);
                            if ($mostCommonError === 'card_not_found' && $errorReasons['card_not_found'] > 100) {
                                $cardCount = Card::count();
                                $message .= "\n\n⚠️ Most cards not found in database!";
                                $message .= "\n\nYou have {$cardCount} cards in database, but CSV has {$errorReasons['card_not_found']} cards that don't exist.";
                                $message .= "\n\nSolution: Run this command to fetch all Grand Archive cards:";
                                $message .= "\n./vendor/bin/sail artisan fetch:grand-archive-data";
                            } elseif ($mostCommonError === 'location_not_found') {
                                $currentLocation = $user->currentLocation();
                                $locationNames = $store->locations()->pluck('name')->implode(', ');
                                $message .= "\n\n⚠️ Location mismatch!";
                                $message .= "\n\nAvailable locations: {$locationNames}";
                                if ($currentLocation) {
                                    $message .= "\nCurrent location: {$currentLocation->name}";
                                }
                                $message .= "\n\nMake sure 'location_name' in CSV matches one of these exactly.";
                            } else {
                                $message .= "\n\nTip: Make sure card names and set codes match exactly. Download the template to see the correct format.";
                            }
                        }

                        if ($success > 0) {
                        Notification::make()
                            ->success()
                            ->title('Import complete')
                                ->body($message)
                                ->send();
                        } else {
                            Notification::make()
                                ->warning()
                                ->title('Import completed with errors')
                                ->body($message)
                            ->send();
                        }
                    }),
                TableAction::make('paste_bulk')
                    ->label('Paste Bulk Rows')
                    ->icon('heroicon-o-clipboard')
                    ->modalWidth('xl')
                    ->form([
                        \Filament\Forms\Components\Textarea::make('rows')
                            ->rows(10)
                            ->required()
                            ->helperText('Paste rows: card_name,set_code,collector_number,edition_slug,is_foil,quantity,quantity_mode,buy_price,sell_price,location_name. One row per line. Tabs or commas are accepted.')
                            ->placeholder("Lightning Bolt\tLOR\t001\tstandard\tFALSE\t5\tadd\t2.50\t9.99\tMain Store"),
                    ])
                    ->action(function (array $data) {
                        $user = Auth::user();
                        $store = $user->currentStore();

                        if (! $store) {
                            Notification::make()->danger()->title('No store selected')->send();
                            return;
                        }

                        $lines = preg_split('/\r\n|\r|\n/', $data['rows'] ?? '', -1, PREG_SPLIT_NO_EMPTY);
                        $success = 0;
                        $failed = 0;

                        foreach ($lines as $line) {
                            $delimiter = str_contains($line, '\t') ? "\t" : ',';
                            $parts = array_map('trim', str_getcsv($line, $delimiter));

                            if (count($parts) < 4) {
                                $failed++;
                                continue;
                            }

                            // Parse fields (support both old and new format)
                            $cardName = trim($parts[0] ?? '');
                            $setCode = strtoupper(trim($parts[1] ?? ''));
                            $isFoil = false;
                            $quantity = null;
                            $quantityMode = 'add';
                            $collectorNumber = '';
                            $editionSlug = '';
                            $buyPrice = null;
                            $sellPrice = null;
                            $locationName = '';

                            // Detect format: if 3rd field looks like boolean, it's old format
                            if (count($parts) >= 4 && in_array(strtolower(trim($parts[2])), ['true', 'false', '0', '1'])) {
                                // Old format: card_number, set_code, foil, quantity, sell_price, location_name
                                $cardNumber = trim($parts[0]);
                                $setCode = strtoupper(trim($parts[1]));
                                $isFoil = filter_var($parts[2], FILTER_VALIDATE_BOOLEAN);
                                $quantity = (int) ($parts[3] ?? 0);
                                $sellPrice = !empty($parts[4]) ? (float) $parts[4] : null;
                                $locationName = trim($parts[5] ?? '');

                            $card = Card::where('card_number', $cardNumber)
                                ->where('set_code', $setCode)
                                ->first();
                            } else {
                                // New format: card_name, set_code, collector_number, edition_slug, is_foil, quantity, quantity_mode, buy_price, sell_price, location_name
                                $cardName = trim($parts[0]);
                                $setCode = strtoupper(trim($parts[1]));
                                $collectorNumber = trim($parts[2] ?? '');
                                $editionSlug = trim($parts[3] ?? '');
                                $isFoil = filter_var($parts[4] ?? false, FILTER_VALIDATE_BOOLEAN);
                                $quantity = !empty($parts[5]) ? (int) $parts[5] : null;
                                $quantityMode = strtolower(trim($parts[6] ?? 'add'));
                                $buyPrice = !empty($parts[7]) ? (float) $parts[7] : null;
                                $sellPrice = !empty($parts[8]) ? (float) $parts[8] : null;
                                $locationName = trim($parts[9] ?? '');
                                
                                $cardQuery = Card::where('name', $cardName)
                                    ->where('set_code', $setCode);
                                
                                if ($collectorNumber) {
                                    $cardQuery->where('card_number', $collectorNumber);
                                }
                                
                                $card = $cardQuery->first();
                            }

                            if (! $card) {
                                $failed++;
                                continue;
                            }

                            // Find edition if specified (new format only)
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

                            $location = $user->currentLocation();
                            if ($locationName !== '') {
                                $location = $store->locations()->where('name', $locationName)->first();
                            }

                            if (! $location || $location->store_id !== $store->id) {
                                $failed++;
                                continue;
                            }

                            if ($quantity === null || $quantity <= 0) {
                                // Price-only update
                                if ($buyPrice !== null || $sellPrice !== null) {
                                    $existingInventory = Inventory::where('card_id', $card->id)
                                        ->where('location_id', $location->id)
                                        ->where('is_foil', $isFoil)
                                        ->when($editionId, fn($q) => $q->where('edition_id', $editionId))
                                        ->first();
                                    
                                    if ($existingInventory) {
                                        if ($buyPrice !== null) $existingInventory->buy_price = $buyPrice;
                                        if ($sellPrice !== null) $existingInventory->sell_price = $sellPrice;
                                        $existingInventory->save();
                                        $success++;
                                    }
                                }
                                continue;
                            }

                            $inventory = Inventory::firstOrNew([
                                'card_id' => $card->id,
                                'location_id' => $location->id,
                                'edition_id' => $editionId,
                                'is_foil' => $isFoil,
                            ]);

                            // Handle quantity mode
                            if ($quantityMode === 'add') {
                            $inventory->quantity = ($inventory->quantity ?? 0) + $quantity;
                            } else {
                                $inventory->quantity = $quantity;
                            }

                            if ($buyPrice !== null) {
                                $inventory->buy_price = $buyPrice;
                            }
                            if ($sellPrice !== null) {
                                $inventory->sell_price = $sellPrice;
                            }

                            $inventory->save();
                            $success++;
                        }

                        Notification::make()
                            ->success()
                            ->title('Paste import complete')
                            ->body("{$success} rows added/updated, {$failed} failed")
                            ->send();
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('edit_inline')
                    ->label('Quick Edit')
                    ->icon('heroicon-o-pencil-square')
                    ->color('primary')
                    ->modalHeading('Edit Inventory')
                    ->form([
                        \Filament\Forms\Components\TextInput::make('quantity')
                            ->numeric()
                            ->minValue(0)
                            ->required()
                            ->label('Quantity'),
                        \Filament\Forms\Components\TextInput::make('sell_price')
                            ->numeric()
                            ->prefix('$')
                            ->label('Sell Price'),
                        \Filament\Forms\Components\Select::make('location_id')
                            ->label('Location')
                            ->options(fn () => Auth::user()?->currentStore()?->locations()->pluck('name', 'id') ?? [])
                            ->required(),
                    ])
                    ->action(function (Inventory $record, array $data) {
                        $user = Auth::user();
                        $store = $user->currentStore();
                        $location = Location::find($data['location_id']);

                        if (! $store || ! $location || $location->store_id !== $store->id) {
                            Notification::make()->danger()->title('Invalid location')->send();
                            return;
                        }

                        $record->update([
                            'quantity' => (int) $data['quantity'],
                            'sell_price' => $data['sell_price'] !== '' ? $data['sell_price'] : null,
                            'location_id' => $location->id,
                        ]);

                        Notification::make()->success()->title('Inventory updated')->send();
                    }),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('updated_at', 'desc')
            ->paginationPageOptions([25, 50, 100]);
    }

    public function getHeading(): string
    {
        return 'Bulk Updates';
    }

    public function getSubheading(): ?string
    {
        $store = Auth::user()?->currentStore();
        return $store ? "Upload, paste, or inline edit for {$store->name}" : 'Upload, paste, or inline edit';
    }
}
