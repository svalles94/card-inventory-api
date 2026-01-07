<?php

namespace App\Filament\Store\Resources\StoreInventoryResource\Pages;

use App\Filament\Store\Resources\StoreInventoryResource;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Auth;
use App\Models\Inventory;
use App\Models\Location;
use App\Models\Card;
use App\Models\Edition;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ListStoreInventories extends ListRecords
{
    protected static string $resource = StoreInventoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->authorize(function () {
                    return auth()->user()->can('create', \App\Models\Inventory::class);
                }),
            Actions\Action::make('download_template')
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

                        // Get all cards from all games (or you could filter by game)
                        $cards = Card::orderBy('game')->orderBy('name')->get();
                        $locationId = $currentLocation?->id;

                        foreach ($cards as $card) {
                            $editions = $card->editions()->orderByDesc('last_update')->get();
                            
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
                                    $card->set_code ?? '',
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
                                        $card->set_code ?? '',
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
                                        $card->set_code ?? '',
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
            Actions\Action::make('bulk_upload')
                ->label('Bulk Upload CSV')
                ->icon('heroicon-o-arrow-up-tray')
                ->modalWidth('lg')
                ->form([
                    \Filament\Forms\Components\FileUpload::make('csv')
                        ->label('CSV File')
                        ->acceptedFileTypes(['text/csv', 'text/plain'])
                        ->directory('tmp')
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
                    $fullPath = Storage::path($filePath);

                    if (! file_exists($fullPath)) {
                        Notification::make()->danger()->title('File not found')->send();
                        return;
                    }

                    $handle = fopen($fullPath, 'r');
                    if (! $handle) {
                        Notification::make()->danger()->title('Unable to read file')->send();
                        return;
                    }

                    $header = fgetcsv($handle);
                    $required = ['card_name', 'set_code', 'is_foil', 'quantity'];

                    if (! $header || count(array_intersect($required, $header)) < count($required)) {
                        fclose($handle);
                        Notification::make()->danger()->title('CSV header missing required columns')->body('Required: card_name, set_code, is_foil, quantity')->send();
                        return;
                    }

                    $success = 0;
                    $failed = 0;

                    while (($row = fgetcsv($handle)) !== false) {
                        if (count($row) === 1 && trim($row[0]) === '') {
                            continue; // skip blank lines
                        }

                        $record = array_combine($header, $row);
                        if (! $record) {
                            $failed++;
                            continue;
                        }

                        // Parse required fields
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

                        if (! $cardName || ! $setCode) {
                            $failed++;
                            continue;
                        }

                        // Find card
                        $cardQuery = Card::where('name', $cardName)
                            ->where('set_code', $setCode);
                        
                        if ($collectorNumber) {
                            $cardQuery->where('card_number', $collectorNumber);
                        }
                        
                        $card = $cardQuery->first();

                        if (! $card) {
                            $failed++;
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
                            continue;
                        }

                        // Skip if quantity is empty (allows price-only updates)
                        if ($quantity === null) {
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
                    Storage::delete($filePath);

                    Notification::make()
                        ->success()
                        ->title('Import complete')
                        ->body("{$success} rows added/updated, {$failed} failed")
                        ->send();
                }),
            Actions\Action::make('quick_add')
                ->label('Quick Add')
                ->icon('heroicon-o-bolt')
                ->modalWidth('lg')
                ->form([
                    \Filament\Forms\Components\Select::make('card_id')
                        ->label('Card')
                        ->relationship('card', 'name')
                        ->searchable()
                        ->preload()
                        ->required()
                        ->getOptionLabelFromRecordUsing(fn ($record) => $record->name),
                    \Filament\Forms\Components\Select::make('location_id')
                        ->label('Location')
                        ->options(function () {
                            $store = Auth::user()->currentStore();
                            if (! $store) {
                                return [];
                            }
                            return $store->locations()->pluck('name', 'id');
                        })
                        ->default(Auth::user()->currentLocation()?->id)
                        ->required()
                        ->helperText('Defaults to your current location'),
                    \Filament\Forms\Components\Toggle::make('is_foil')
                        ->label('Foil')
                        ->default(false),
                    \Filament\Forms\Components\TextInput::make('quantity')
                        ->numeric()
                        ->required()
                        ->default(1)
                        ->minValue(1)
                        ->label('Qty'),
                    \Filament\Forms\Components\TextInput::make('sell_price')
                        ->numeric()
                        ->prefix('$')
                        ->label('Sell Price (optional)'),
                ])
                ->action(function (array $data) {
                    $user = Auth::user();
                    $store = $user->currentStore();

                    if (! $store) {
                        Notification::make()->danger()->title('No store selected')->send();
                        return;
                    }

                    $location = Location::find($data['location_id']);

                    if (! $location || $location->store_id !== $store->id) {
                        Notification::make()->danger()->title('Invalid location for this store')->send();
                        return;
                    }

                    $inventory = Inventory::firstOrNew([
                        'location_id' => $location->id,
                        'card_id' => $data['card_id'],
                        'is_foil' => $data['is_foil'] ?? false,
                    ]);

                    $inventory->quantity = ($inventory->quantity ?? 0) + (int) $data['quantity'];
                    if (isset($data['sell_price']) && $data['sell_price'] !== null && $data['sell_price'] !== '') {
                        $inventory->sell_price = $data['sell_price'];
                    }

                    $inventory->save();

                    Notification::make()
                        ->success()
                        ->title('Added to inventory')
                        ->body('Saved to ' . $location->name)
                        ->send();
                }),
        ];
    }
}

