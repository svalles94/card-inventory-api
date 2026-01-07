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
                        $headers = [
                            'Content-Type' => 'text/csv',
                            'Content-Disposition' => 'attachment; filename="inventory-bulk-template.csv"',
                        ];

                        $callback = function () {
                            $output = fopen('php://output', 'w');
                            fputcsv($output, ['card_name', 'set_code', 'collector_number', 'edition_slug', 'is_foil', 'quantity', 'quantity_mode', 'buy_price', 'sell_price', 'location_name']);
                            fputcsv($output, ['Lightning Bolt', 'LOR', '001', 'standard', 'FALSE', '5', 'add', '2.50', '9.99', 'Main Store']);
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
                                continue;
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
