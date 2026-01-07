<?php

namespace App\Filament\Store\Pages;

use App\Models\Card;
use App\Models\Inventory;
use App\Models\Location;
use App\Models\Edition;
use Illuminate\Support\Facades\Auth;
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
                        'Grand Archive' => 'Grand Archive',
                        'Gundam' => 'Gundam',
                        'Riftbound' => 'Riftbound',
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
        $game = $this->data['game'] ?? null;
        $locationId = $this->data['location'] ?? null;

        if (!$game || !$locationId) {
            Notification::make()
                ->title('Please select both a game and location first')
                ->danger()
                ->send();
            return response()->stream(function () {}, 200);
        }

        $location = Location::find($locationId);
        $cards = Card::where('game', $game)->orderBy('name')->get();

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
            $editions = $card->editions()->orderByDesc('last_update')->get();
            
            // If no editions, create a row with just the card info
            if ($editions->isEmpty()) {
                $inventory = Inventory::where('card_id', $card->id)
                    ->where('location_id', $locationId)
                    ->where('is_foil', false)
                    ->first();
                
                $csv->insertOne([
                    $card->name,
                    $card->set_code ?? '',
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
                    // Non-foil row
                    $inventory = Inventory::where('card_id', $card->id)
                        ->where('edition_id', $edition->id)
                        ->where('location_id', $locationId)
                        ->where('is_foil', false)
                        ->first();
                    
                    $csv->insertOne([
                        $card->name,
                        $card->set_code ?? '',
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
                        $card->set_code ?? '',
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
        $filePath = Storage::disk('local')->path($data['csv_file']);

        try {
            $csv = Reader::createFromPath($filePath, 'r');
            $csv->setHeaderOffset(0);
            $records = $csv->getRecords();

            $updated = 0;
            $created = 0;
            $errors = [];
            $user = Auth::user();
            $store = $user->currentStore();

            foreach ($records as $offset => $record) {
                try {
                    // Parse required fields
                    $cardName = trim($record['card_name'] ?? '');
                    $setCode = strtoupper(trim($record['set_code'] ?? ''));
                    $isFoil = filter_var($record['is_foil'] ?? false, FILTER_VALIDATE_BOOLEAN);
                    $quantity = !empty($record['quantity']) ? (int) $record['quantity'] : null;
                    $quantityMode = strtolower(trim($record['quantity_mode'] ?? 'replace')); // 'add' or 'replace'
                    
                    // Optional fields
                    $collectorNumber = trim($record['collector_number'] ?? '');
                    $editionSlug = trim($record['edition_slug'] ?? '');
                    $buyPrice = !empty($record['buy_price']) ? (float) $record['buy_price'] : null;
                    $sellPrice = !empty($record['sell_price']) ? (float) $record['sell_price'] : null;
                    $locationName = trim($record['location_name'] ?? '');

                    // Validation
                    if (empty($cardName) || empty($setCode)) {
                        $errors[] = "Row " . ($offset + 2) . ": Missing card_name or set_code";
                        continue;
                    }

                    // Find card
                    $cardQuery = Card::where('name', $cardName)
                        ->where('set_code', $setCode);
                    
                    if ($collectorNumber) {
                        $cardQuery->where('card_number', $collectorNumber);
                    }
                    
                    $card = $cardQuery->first();
                    
                    if (!$card) {
                        $errors[] = "Row " . ($offset + 2) . ": Card not found: {$cardName} ({$setCode})";
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
                    } else {
                        $updated++;
                    }
                } catch (\Exception $e) {
                    $errors[] = "Row " . ($offset + 2) . ": {$e->getMessage()}";
                }
            }

            // Clean up the uploaded file
            Storage::disk('local')->delete($data['csv_file']);

            if (empty($errors)) {
                Notification::make()
                    ->title('Import successful!')
                    ->body("Created {$created} new inventory records and updated {$updated} existing records.")
                    ->success()
                    ->send();
            } else {
                Notification::make()
                    ->title('Import completed with errors')
                    ->body("Created {$created}, updated {$updated}. Errors: " . implode('; ', array_slice($errors, 0, 3)))
                    ->warning()
                    ->send();
            }

            // Reset the form
            $this->form->fill();

        } catch (\Exception $e) {
            Notification::make()
                ->title('Import failed')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }
}
