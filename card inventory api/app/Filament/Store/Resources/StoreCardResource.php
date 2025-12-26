<?php

namespace App\Filament\Store\Resources;

use App\Filament\Store\Resources\StoreCardResource\Pages;
use App\Models\Card;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class StoreCardResource extends Resource
{
    protected static ?string $model = Card::class;

    protected static ?string $navigationIcon = 'heroicon-o-credit-card';
    
    protected static ?string $navigationLabel = 'Cards';
    
    protected static ?string $navigationGroup = 'Inventory';
    
    protected static ?int $navigationSort = 0; // Show before Inventory

    // Hide from navigation if you only want it accessible when adding inventory
    // protected static bool $shouldRegisterNavigation = false;

    public static function form(Form $form): Form
    {
        // Read-only view for store owners (they can't edit cards, only view)
        return $form
            ->schema([
                Forms\Components\Section::make('Card Information')
                    ->schema([
                        Forms\Components\Placeholder::make('name')
                            ->label('Card Name')
                            ->content(fn (Card $record): string => $record->name),
                        Forms\Components\Placeholder::make('id')
                            ->label('Card ID')
                            ->content(fn (Card $record): string => $record->id),
                        Forms\Components\Placeholder::make('image')
                            ->label('Card Image')
                            ->content(function (Card $record) {
                                $imageUrl = $record->image_url ?? '/images/card-placeholder.png';
                                return new \Illuminate\Support\HtmlString(
                                    '<a href="' . htmlspecialchars($imageUrl) . '" target="_blank">' .
                                    '<img src="' . htmlspecialchars($imageUrl) . '" alt="' . htmlspecialchars($record->name) . '" style="max-width: 300px; height: auto; border-radius: 8px;" />' .
                                    '</a>'
                                );
                            }),
                    ])
                    ->columns(2),
                
                Forms\Components\Section::make('Card Details')
                    ->schema([
                        Forms\Components\Placeholder::make('elements')
                            ->label('Elements')
                            ->content(fn (Card $record): string => is_array($record->elements) ? implode(', ', $record->elements) : ($record->elements ?? 'N/A')),
                        Forms\Components\Placeholder::make('classes')
                            ->label('Classes')
                            ->content(fn (Card $record): string => is_array($record->classes) ? implode(', ', $record->classes) : ($record->classes ?? 'N/A')),
                        Forms\Components\Placeholder::make('rarity')
                            ->label('Rarity')
                            ->content(fn (Card $record): string => match($record->rarity) {
                                1 => 'Common',
                                2 => 'Uncommon',
                                3 => 'Rare',
                                4 => 'Super Rare',
                                default => 'Unknown',
                            }),
                        Forms\Components\Placeholder::make('cost')
                            ->label('Cost')
                            ->content(fn (Card $record): string => $record->cost ?? 'N/A'),
                    ])
                    ->columns(4),
                
                Forms\Components\Section::make('Pricing')
                    ->schema([
                        Forms\Components\Placeholder::make('market_price')
                            ->label('Market Price')
                            ->content(function (Card $record): string {
                                $latestPrice = $record->cardPrices()
                                    ->whereNotNull('market_price')
                                    ->orderBy('updated_at', 'desc')
                                    ->first();
                                return $latestPrice ? '$' . number_format($latestPrice->market_price, 2) : 'N/A';
                            }),
                        Forms\Components\Placeholder::make('editions_count')
                            ->label('Editions')
                            ->content(fn (Card $record): string => (string) $record->editions()->count()),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('image_url')
                    ->label('Image')
                    ->height(150)
                    ->width(100)
                    ->defaultImageUrl('/images/card-placeholder.png')
                    ->url(fn ($record): ?string => $record->image_url)
                    ->openUrlInNewTab()
                    ->visibleFrom('md'),

                Tables\Columns\TextColumn::make('name')
                    ->label('Card Name')
                    ->searchable()
                    ->sortable()
                    ->size('lg')
                    ->weight('bold')
                    ->wrap(),

                Tables\Columns\TextColumn::make('elements')
                    ->label('Elements')
                    ->badge()
                    ->formatStateUsing(function ($state) {
                        if (is_array($state)) {
                            return implode(', ', $state);
                        }
                        return $state ?? 'N/A';
                    })
                    ->color('info')
                    ->visibleFrom('lg'),

                Tables\Columns\TextColumn::make('classes')
                    ->label('Classes')
                    ->badge()
                    ->formatStateUsing(function ($state) {
                        if (is_array($state)) {
                            return implode(', ', $state);
                        }
                        return $state ?? 'N/A';
                    })
                    ->color('warning')
                    ->visibleFrom('lg'),

                Tables\Columns\TextColumn::make('market_price')
                    ->label('Market Price')
                    ->state(function (Card $record): ?float {
                        $latestPrice = $record->cardPrices()
                            ->whereNotNull('market_price')
                            ->orderBy('updated_at', 'desc')
                            ->first();
                        return $latestPrice?->market_price;
                    })
                    ->money('USD')
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        // Sort by latest price
                        return $query->join('card_prices', 'cards.id', '=', 'card_prices.card_id')
                            ->select('cards.*')
                            ->orderBy('card_prices.market_price', $direction)
                            ->groupBy('cards.id');
                    })
                    ->toggleable(),

                Tables\Columns\TextColumn::make('editions_count')
                    ->label('Editions')
                    ->counts('editions')
                    ->badge()
                    ->color('success')
                    ->sortable(),

                Tables\Columns\TextColumn::make('rarity')
                    ->label('Rarity')
                    ->badge()
                    ->formatStateUsing(fn ($state) => match($state) {
                        1 => 'Common',
                        2 => 'Uncommon',
                        3 => 'Rare',
                        4 => 'Super Rare',
                        default => 'Unknown',
                    })
                    ->color(fn ($state) => match($state) {
                        1 => 'gray',
                        2 => 'success',
                        3 => 'warning',
                        4 => 'danger',
                        default => 'gray',
                    })
                    ->visibleFrom('md'),
            ])
            ->defaultSort('name')
            ->filters([
                Tables\Filters\SelectFilter::make('rarity')
                    ->label('Rarity')
                    ->options([
                        1 => 'Common',
                        2 => 'Uncommon',
                        3 => 'Rare',
                        4 => 'Super Rare',
                    ]),
                
                Tables\Filters\Filter::make('has_price')
                    ->label('Has Market Price')
                    ->query(fn (Builder $query): Builder => $query->whereHas('cardPrices', fn ($q) => $q->whereNotNull('market_price')))
                    ->toggle(),
            ])
            ->filtersLayout(Tables\Enums\FiltersLayout::AboveContentCollapsible)
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\Action::make('add_to_inventory')
                    ->label('Add to Inventory')
                    ->icon('heroicon-o-plus-circle')
                    ->color('success')
                    ->url(function (Card $record) {
                        return \App\Filament\Store\Resources\StoreInventoryResource::getUrl('create', [
                            'card_id' => $record->id,
                        ]);
                    })
                    ->visible(function () {
                        $currentStore = auth()->user()->currentStore();
                        if (!$currentStore) return false;
                        $user = auth()->user();
                        return $user->hasRole($currentStore, 'owner') || $user->hasRole($currentStore, 'admin');
                    }),
            ])
            ->bulkActions([
                // No bulk actions for cards in store panel
            ])
            ->emptyStateHeading('No cards found')
            ->emptyStateDescription('Cards will appear here once they are synced from the Grand Archive API.')
            ->emptyStateIcon('heroicon-o-credit-card');
    }

    public static function getRelations(): array
    {
        return [
            // Show editions when viewing a card
            \App\Filament\Store\Resources\StoreCardResource\RelationManagers\EditionsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStoreCards::route('/'),
            'view' => Pages\ViewStoreCard::route('/{record}'),
        ];
    }
}

