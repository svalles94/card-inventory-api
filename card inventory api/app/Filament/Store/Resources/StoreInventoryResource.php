<?php

namespace App\Filament\Store\Resources;

use App\Filament\Store\Resources\StoreInventoryResource\Pages;
use App\Models\Inventory;
use App\Models\Card;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class StoreInventoryResource extends Resource
{
    protected static ?string $model = Inventory::class;

    protected static ?string $navigationIcon = 'heroicon-o-cube';
    
    protected static ?string $navigationGroup = 'Inventory';
    
    protected static ?int $navigationSort = 1;

    public static function getLatestMarketPrice(string $cardId): ?float
    {
        return Cache::remember("card:{$cardId}:latest_market_price", 300, function () use ($cardId) {
            return Card::find($cardId)?->cardPrices()
                ->whereNotNull('market_price')
                ->orderBy('updated_at', 'desc')
                ->value('market_price');
        });
    }

    public static function form(Form $form): Form
    {
        $currentStore = auth()->user()->currentStore();
        $currentLocationId = auth()->user()->currentLocation()?->id;
        
        if (!$currentStore) {
            return $form->schema([]);
        }
        
        return $form
            ->schema([
                Forms\Components\Section::make('Card Selection')
                    ->schema([
                        Forms\Components\Select::make('location_id')
                            ->relationship('location', 'name', fn ($query) => $query->where('store_id', $currentStore->id))
                            ->required()
                            ->searchable()
                            ->preload()
                            ->default($currentLocationId)
                            ->label('Location')
                            ->helperText($currentLocationId ? 'Defaulted from your selected location' : 'Pick a location for this inventory item'),
                        Forms\Components\Select::make('card_id')
                            ->relationship('card', 'name')
                            ->required()
                            ->searchable()
                            ->preload()
                            ->getOptionLabelFromRecordUsing(fn (Card $record) => "{$record->name} ({$record->set_code} - {$record->card_number})")
                            ->label('Card')
                            ->helperText('Or browse cards to find the one you want')
                            ->suffixAction(
                                Forms\Components\Actions\Action::make('browse_cards')
                                    ->icon('heroicon-o-magnifying-glass')
                                    ->url(\App\Filament\Store\Resources\StoreCardResource::getUrl('index'))
                                    ->openUrlInNewTab()
                            )
                            ->reactive()
                            ->afterStateUpdated(function (callable $set, $state) {
                                if (! $state) {
                                    return;
                                }

                                $latest = static::getLatestMarketPrice($state);

                                if ($latest !== null) {
                                    $set('market_price', $latest);
                                }
                            }),
                    ])->columns(2),
                    
                Forms\Components\Section::make('Quantity')
                    ->schema([
                        Forms\Components\TextInput::make('quantity')
                            ->required()
                            ->numeric()
                            ->default(0)
                            ->minValue(0)
                            ->label('Stock Quantity')
                            ->helperText(function () {
                                $currentStore = auth()->user()->currentStore();
                                if (!$currentStore) return 'Current number of cards in stock';
                                
                                $user = auth()->user();
                                // Base users can't set quantity directly, only adjust
                                if ($user->hasRole($currentStore, 'base')) {
                                    return 'Base users can only adjust quantities, not set them directly';
                                }
                                return 'Current number of cards in stock';
                            })
                            ->disabled(function () {
                                $currentStore = auth()->user()->currentStore();
                                if (!$currentStore) return false;
                                
                                $user = auth()->user();
                                // Base users can't set quantity directly
                                return $user->hasRole($currentStore, 'base');
                            })
                            ->extraInputAttributes(['style' => 'font-size: 1.5rem; font-weight: bold;']),
                    ]),
                    
                Forms\Components\Section::make('Pricing')
                    ->schema([
                        Forms\Components\TextInput::make('buy_price')
                            ->numeric()
                            ->prefix('$')
                            ->label('Buy Price')
                            ->helperText('What you paid for it'),
                        Forms\Components\TextInput::make('sell_price')
                            ->numeric()
                            ->prefix('$')
                            ->label('Your Price')
                            ->helperText('What you sell it for'),
                        Forms\Components\Placeholder::make('market_price_display')
                            ->label('TCGPlayer Market Price')
                            ->content(function (callable $get) {
                                $cardId = $get('card_id');
                                if (! $cardId) {
                                    return 'N/A';
                                }

                                $price = static::getLatestMarketPrice($cardId);
                                return $price ? '$' . number_format($price, 2) : 'N/A';
                            })
                            ->helperText('Reference only - from TCGPlayer API'),
                    ])->columns(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        $currentStore = auth()->user()->currentStore();
        
        if (!$currentStore) {
            return $table;
        }
        
        return $table
            ->modifyQueryUsing(function (Builder $query) use ($currentStore) {
                // Only show inventory for locations in the current store
                return $query->whereHas('location', function ($q) use ($currentStore) {
                    $q->where('store_id', $currentStore->id);
                });
            })
            ->columns([
                Tables\Columns\ImageColumn::make('card.image')
                    ->label('Image')
                    ->size(80)
                    ->defaultImageUrl('/images/card-placeholder.png')
                    ->visibleFrom('md'),
                    
                Tables\Columns\Layout\Stack::make([
                    Tables\Columns\TextColumn::make('card.name')
                        ->label('Card Name')
                        ->searchable()
                        ->sortable()
                        ->size('lg')
                        ->weight('bold')
                        ->wrap(),
                        
                    Tables\Columns\Layout\Stack::make([
                        Tables\Columns\TextColumn::make('card.set_code')
                            ->badge()
                            ->color('info')
                            ->size('sm'),
                        Tables\Columns\TextColumn::make('card.card_number')
                            ->prefix('#')
                            ->size('sm')
                            ->color('gray'),
                    ])->space(1),
                ])->space(1),
                    
                Tables\Columns\TextColumn::make('location.name')
                    ->label('Location')
                    ->searchable()
                    ->sortable()
                    ->toggleable()
                    ->visibleFrom('lg'),
                    
                Tables\Columns\TextColumn::make('quantity')
                    ->label('On Hand')
                    ->sortable()
                    ->size('xl')
                    ->weight('bold')
                    ->color(fn ($state) => match(true) {
                        $state === 0 => 'danger',
                        $state < 4 => 'warning',
                        $state < 10 => 'info',
                        default => 'success',
                    })
                    ->badge(),
                    
                Tables\Columns\TextColumn::make('sell_price')
                    ->label('Your Price')
                    ->money('USD')
                    ->sortable()
                    ->toggleable()
                    ->visibleFrom('md'),
                    
                Tables\Columns\TextColumn::make('total_value')
                    ->label('Total Value')
                    ->state(fn (Inventory $record): float => ($record->sell_price ?? 0) * $record->quantity)
                    ->money('USD')
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query->orderByRaw("(sell_price * quantity) {$direction}");
                    })
                    ->toggleable()
                    ->visibleFrom('lg'),
                    
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Last Updated')
                    ->dateTime('M j, g:i A')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->contentGrid([
                'md' => 2,
                'lg' => 3,
                'xl' => 4,
            ])
            ->defaultSort('card.name')
            ->filters([
                Tables\Filters\SelectFilter::make('location')
                    ->relationship('location', 'name', function ($query) use ($currentStore) {
                        return $query->where('store_id', $currentStore->id);
                    })
                    ->label('Location')
                    ->searchable()
                    ->preload(),
                    
                Tables\Filters\Filter::make('in_stock')
                    ->label('Cards On Hand')
                    ->query(fn (Builder $query): Builder => $query->where('quantity', '>', 0))
                    ->toggle()
                    ->default(),
                    
                Tables\Filters\Filter::make('low_stock')
                    ->label('Low Stock (< 4)')
                    ->query(fn (Builder $query): Builder => $query->where('quantity', '<', 4)->where('quantity', '>', 0))
                    ->toggle(),
                    
                Tables\Filters\Filter::make('out_of_stock')
                    ->label('Out of Stock')
                    ->query(fn (Builder $query): Builder => $query->where('quantity', 0))
                    ->toggle(),
            ])
            ->filtersLayout(Tables\Enums\FiltersLayout::AboveContentCollapsible)
            ->actions([
                Tables\Actions\Action::make('adjust_quantity')
                    ->label('Adjust')
                    ->icon('heroicon-o-calculator')
                    ->color('primary')
                    ->requiresConfirmation()
                    ->visible(fn (Inventory $record) => auth()->user()->can('adjustQuantity', $record))
                    ->form([
                        Forms\Components\TextInput::make('adjustment')
                            ->label('Quantity Adjustment')
                            ->helperText('Enter positive number to add, negative to subtract')
                            ->numeric()
                            ->required()
                            ->extraInputAttributes(['style' => 'font-size: 1.25rem;']),
                    ])
                    ->action(function (Inventory $record, array $data): void {
                        $this->authorize('adjustQuantity', $record);
                        $newQuantity = max(0, $record->quantity + $data['adjustment']);
                        $record->update(['quantity' => $newQuantity]);
                    })
                    ->successNotificationTitle('Quantity updated!'),
                    
                Tables\Actions\EditAction::make()
                    ->label('Edit')
                    ->visible(fn (Inventory $record) => auth()->user()->can('update', $record)),
                    
                Tables\Actions\DeleteAction::make()
                    ->visible(fn (Inventory $record) => auth()->user()->can('delete', $record)),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->requiresConfirmation(),
                ]),
            ])
            ->emptyStateHeading('No inventory yet')
            ->emptyStateDescription('Add your first card to start tracking inventory.')
            ->emptyStateIcon('heroicon-o-cube');
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStoreInventories::route('/'),
            'create' => Pages\CreateStoreInventory::route('/create'),
            'edit' => Pages\EditStoreInventory::route('/{record}/edit'),
        ];
    }
}

