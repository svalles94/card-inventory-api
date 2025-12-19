<?php

namespace App\Filament\Pages;

use App\Models\Card;
use App\Models\Inventory;
use App\Models\Location;
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
    protected static string $view = 'filament.pages.bulk-inventory-update';

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
                    ->helperText('Upload the CSV file you downloaded and filled out with inventory quantities.'),
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
        
        // Add headers
        $csv->insertOne([
            'card_id',
            'card_name',
            'set_name',
            'rarity',
            'current_quantity',
            'new_quantity',
            'buy_price',
            'sell_price',
            'market_price',
        ]);

        // Add card rows
        foreach ($cards as $card) {
            // Get existing inventory for this location if it exists
            $inventory = Inventory::where('card_id', $card->id)
                ->where('location_id', $locationId)
                ->first();

            $csv->insertOne([
                $card->id,
                $card->name,
                $card->set_name ?? '',
                $card->rarity ?? '',
                $inventory ? $inventory->quantity : 0,
                '', // Empty for user to fill
                $inventory ? $inventory->buy_price : '',
                $inventory ? $inventory->sell_price : '',
                $inventory ? $inventory->market_price : '',
            ]);
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

            foreach ($records as $offset => $record) {
                // Skip if new_quantity is empty
                if (empty($record['new_quantity']) || $record['new_quantity'] === '') {
                    continue;
                }

                $cardId = $record['card_id'];
                $newQuantity = (int) $record['new_quantity'];
                $buyPrice = !empty($record['buy_price']) ? (float) $record['buy_price'] : null;
                $sellPrice = !empty($record['sell_price']) ? (float) $record['sell_price'] : null;
                $marketPrice = !empty($record['market_price']) ? (float) $record['market_price'] : null;

                try {
                    $inventory = Inventory::updateOrCreate(
                        [
                            'card_id' => $cardId,
                            'location_id' => $locationId,
                        ],
                        [
                            'quantity' => $newQuantity,
                            'buy_price' => $buyPrice,
                            'sell_price' => $sellPrice,
                            'market_price' => $marketPrice,
                        ]
                    );

                    if ($inventory->wasRecentlyCreated) {
                        $created++;
                    } else {
                        $updated++;
                    }
                } catch (\Exception $e) {
                    $errors[] = "Row {$offset}: {$e->getMessage()}";
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
