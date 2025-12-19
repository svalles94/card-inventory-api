<?php

namespace App\Filament\Pages;

use App\Models\Card;
use App\Models\Inventory;
use App\Models\Location;
use Filament\Pages\Page;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextInputColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Filter;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class ManageInventory extends Page implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;
    
    protected static ?string $navigationIcon = 'heroicon-o-cube';
    
    protected static ?string $navigationLabel = 'Manage Inventory';
    
    protected static ?string $title = 'Manage Inventory';
    
    protected static ?int $navigationSort = 1;

    protected static string $view = 'filament.pages.manage-inventory';
    
    public ?int $selectedLocationId = null;
    public string $selectedGame = 'grand-archive';
    public bool $editMode = false;
    
    public function mount(): void
    {
        // Get user's first accessible location
        $firstLocation = $this->getAccessibleLocations()->first();
        $this->selectedLocationId = $firstLocation?->id;
    }
    
    public function getAccessibleLocations()
    {
        // For now, return all locations. Later add user-location permissions
        return Location::with('store')->get();
    }
    
    public function selectLocation(int $locationId): void
    {
        $this->selectedLocationId = $locationId;
        $this->resetTable();
    }
    
    public function selectGame(string $game): void
    {
        $this->selectedGame = $game;
        $this->resetTable();
    }
    
    public function toggleEditMode(): void
    {
        $this->editMode = !$this->editMode;
        
        Notification::make()
            ->title($this->editMode ? 'Edit mode enabled' : 'Edit mode disabled')
            ->success()
            ->send();
    }
    
    public function table(Table $table): Table
    {
        return $table
            ->query(
                Card::query()
                    ->where('game', $this->selectedGame)
                    ->with(['inventory' => function ($query) {
                        if ($this->selectedLocationId) {
                            $query->where('location_id', $this->selectedLocationId);
                        }
                    }])
            )
            ->columns([
                ImageColumn::make('image')
                    ->label('Card')
                    ->size(80)
                    ->defaultImageUrl('/images/card-placeholder.png'),
                    
                TextColumn::make('name')
                    ->label('Card Name')
                    ->searchable()
                    ->sortable()
                    ->size('lg')
                    ->weight('bold')
                    ->wrap(),
                    
                TextColumn::make('set_code')
                    ->label('Set')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('info'),
                    
                TextColumn::make('card_number')
                    ->label('#')
                    ->searchable()
                    ->sortable(),
                    
                TextColumn::make('types')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn ($state) => is_array($state) ? implode(', ', $state) : $state),
                    
                TextColumn::make('classes')
                    ->label('Class')
                    ->badge()
                    ->color('success')
                    ->formatStateUsing(fn ($state) => is_array($state) ? implode(', ', $state) : $state)
                    ->toggleable(),
                    
                TextColumn::make('elements')
                    ->label('Element')
                    ->badge()
                    ->color('warning')
                    ->formatStateUsing(fn ($state) => is_array($state) ? implode(', ', $state) : $state)
                    ->toggleable(),
                    
                TextColumn::make('rarity')
                    ->label('Rarity')
                    ->badge()
                    ->sortable()
                    ->color(fn ($state) => match($state) {
                        1 => 'gray',
                        2 => 'success', 
                        3 => 'warning',
                        4 => 'danger',
                        default => 'gray',
                    }),
                    
                TextColumn::make('inventory.quantity')
                    ->label('Stock')
                    ->getStateUsing(function (Card $record) {
                        return $record->inventory->first()?->quantity ?? 0;
                    })
                    ->badge()
                    ->size('lg')
                    ->color(fn ($state) => match(true) {
                        $state === 0 => 'gray',
                        $state < 4 => 'danger',
                        $state < 10 => 'warning',
                        default => 'success',
                    })
                    ->sortable()
                    ->visible(fn () => !$this->editMode),
                    
                TextInputColumn::make('inventory.quantity')
                    ->label('Stock (Edit)')
                    ->getStateUsing(function (Card $record) {
                        return $record->inventory->first()?->quantity ?? 0;
                    })
                    ->rules(['numeric', 'min:0'])
                    ->updateStateUsing(function (Card $record, $state) {
                        if (!$this->selectedLocationId) {
                            Notification::make()
                                ->title('Please select a location first')
                                ->danger()
                                ->send();
                            return;
                        }
                        
                        Inventory::updateOrCreate(
                            [
                                'location_id' => $this->selectedLocationId,
                                'card_id' => $record->id,
                            ],
                            [
                                'quantity' => max(0, (int) $state),
                            ]
                        );
                        
                        Notification::make()
                            ->title('Quantity updated!')
                            ->success()
                            ->send();
                    })
                    ->visible(fn () => $this->editMode),
            ])
            ->filters([
                SelectFilter::make('set_code')
                    ->label('Set')
                    ->options(function () {
                        return Card::where('game', $this->selectedGame)
                            ->distinct()
                            ->pluck('set_code', 'set_code')
                            ->toArray();
                    })
                    ->searchable(),
                    
                SelectFilter::make('types')
                    ->label('Type')
                    ->options(function () {
                        $types = Card::where('game', $this->selectedGame)
                            ->whereNotNull('types')
                            ->pluck('types')
                            ->flatten()
                            ->unique()
                            ->sort()
                            ->mapWithKeys(fn ($type) => [$type => $type]);
                        return $types->toArray();
                    })
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            $data['value'],
                            fn (Builder $query, $value): Builder => 
                                $query->whereJsonContains('types', $value)
                        );
                    }),
                    
                SelectFilter::make('classes')
                    ->label('Class')
                    ->options(function () {
                        $classes = Card::where('game', $this->selectedGame)
                            ->whereNotNull('classes')
                            ->pluck('classes')
                            ->flatten()
                            ->unique()
                            ->sort()
                            ->mapWithKeys(fn ($class) => [$class => $class]);
                        return $classes->toArray();
                    })
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            $data['value'],
                            fn (Builder $query, $value): Builder => 
                                $query->whereJsonContains('classes', $value)
                        );
                    }),
                    
                Filter::make('low_stock')
                    ->label('Low Inventory (< 4)')
                    ->toggle()
                    ->query(function (Builder $query): Builder {
                        if ($this->selectedLocationId) {
                            return $query->whereHas('inventory', function ($q) {
                                $q->where('location_id', $this->selectedLocationId)
                                    ->where('quantity', '>', 0)
                                    ->where('quantity', '<', 4);
                            });
                        }
                        return $query;
                    }),
                    
                Filter::make('high_stock')
                    ->label('High Inventory (>= 10)')
                    ->toggle()
                    ->query(function (Builder $query): Builder {
                        if ($this->selectedLocationId) {
                            return $query->whereHas('inventory', function ($q) {
                                $q->where('location_id', $this->selectedLocationId)
                                    ->where('quantity', '>=', 10);
                            });
                        }
                        return $query;
                    }),
                    
                Filter::make('in_stock')
                    ->label('In Stock Only')
                    ->toggle()
                    ->query(function (Builder $query): Builder {
                        if ($this->selectedLocationId) {
                            return $query->whereHas('inventory', function ($q) {
                                $q->where('location_id', $this->selectedLocationId)
                                    ->where('quantity', '>', 0);
                            });
                        }
                        return $query;
                    }),
            ])
            ->filtersLayout(\Filament\Tables\Enums\FiltersLayout::AboveContentCollapsible)
            ->defaultSort('name', 'asc')
            ->persistSortInSession()
            ->persistSearchInSession()
            ->striped();
    }
}
