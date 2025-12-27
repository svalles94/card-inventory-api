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
                    $headers = [
                        'Content-Type' => 'text/csv',
                        'Content-Disposition' => 'attachment; filename="inventory-template.csv"',
                    ];

                    $callback = function () {
                        $output = fopen('php://output', 'w');
                        fputcsv($output, ['card_number', 'set_code', 'foil', 'quantity', 'sell_price', 'location_name(optional)']);
                        fputcsv($output, ['123', 'LOR', 'false', '5', '9.99', 'Main Store']);
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
                        ->helperText('Columns: card_number, set_code, foil (true/false), quantity, sell_price, location_name(optional)')
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
                    $required = ['card_number', 'set_code', 'foil', 'quantity', 'sell_price', 'location_name(optional)'];

                    if (! $header || count(array_intersect($required, $header)) < 4) {
                        fclose($handle);
                        Notification::make()->danger()->title('CSV header missing required columns')->send();
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

                        $cardNumber = trim($record['card_number'] ?? '');
                        $setCode = strtoupper(trim($record['set_code'] ?? ''));
                        $foil = filter_var($record['foil'] ?? false, FILTER_VALIDATE_BOOLEAN);
                        $quantity = (int) ($record['quantity'] ?? 0);
                        $sellPrice = $record['sell_price'] !== '' ? (float) $record['sell_price'] : null;
                        $locationName = trim($record['location_name(optional)'] ?? '');

                        if (! $cardNumber || ! $setCode || $quantity <= 0) {
                            $failed++;
                            continue;
                        }

                        $card = Card::where('card_number', $cardNumber)
                            ->where('set_code', $setCode)
                            ->first();

                        if (! $card) {
                            $failed++;
                            continue;
                        }

                        $location = $user->currentLocation();

                        if ($locationName !== '') {
                            $location = $store->locations()->where('name', $locationName)->first();
                        }

                        if (! $location || $location->store_id !== $store->id) {
                            $failed++;
                            continue;
                        }

                        $inventory = Inventory::firstOrNew([
                            'location_id' => $location->id,
                            'card_id' => $card->id,
                            'is_foil' => $foil,
                        ]);

                        $inventory->quantity = ($inventory->quantity ?? 0) + $quantity;
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

