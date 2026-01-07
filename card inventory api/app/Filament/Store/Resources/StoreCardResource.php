<?php

namespace App\Filament\Store\Resources;

use App\Filament\Store\Resources\StoreCardResource\Pages;
use App\Models\Card;
use App\Support\InventoryUpdateQueue;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Filament\Notifications\Notification;
use App\Models\Inventory;
use App\Models\Edition;

class StoreCardResource extends Resource
{
    protected static ?string $model = Card::class;

    protected static ?string $navigationIcon = 'heroicon-o-credit-card';
    protected static ?string $navigationLabel = 'Cards';
    protected static ?string $navigationGroup = 'Inventory';
    protected static ?int $navigationSort = 0;

    public static function form(Form $form): Form
    {
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
                        Forms\Components\Placeholder::make('image_url')
                            ->label('Card Image')
                            ->content(function (Card $record): HtmlString {
                                $imageUrl = $record->image_url ?? '/images/card-placeholder.png';
                                return new HtmlString(
                                    '<a href="' . htmlspecialchars($imageUrl) . '" target="_blank">'
                                    . '<img src="' . htmlspecialchars($imageUrl) . '" alt="' . htmlspecialchars($record->name) . '" style="max-width: 300px; height: auto; border-radius: 8px;" />'
                                    . '</a>'
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
                            ->content(fn (Card $record): string => match ($record->rarity) {
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
                        Forms\Components\Placeholder::make('editions_count')
                            ->label('Total Editions')
                            ->content(fn (Card $record): string => (string) $record->editions()->count()),
                    ])
                    ->columns(1),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('image_url')
                    ->label('Image')
                    ->height(200)
                    ->width(140)
                    ->defaultImageUrl('/images/card-placeholder.png')
                    ->url(fn (Card $record): ?string => $record->image_url)
                    ->openUrlInNewTab(),
                Tables\Columns\TextColumn::make('name')
                    ->label('Card Name')
                    ->searchable()
                    ->sortable()
                    ->size('lg')
                    ->weight('bold')
                    ->wrap(),
                Tables\Columns\TextColumn::make('set_code')
                    ->label('Set')
                    ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('card_number')
                    ->label('#')
                    ->sortable(),
                Tables\Columns\TextColumn::make('elements')
                    ->label('Elements')
                    ->visible(false),
                Tables\Columns\TextColumn::make('classes')
                    ->label('Classes')
                    ->visible(false),
                Tables\Columns\TextColumn::make('foil_market_price')
                    ->label('TCGPlayer Market (Foil)')
                    ->state(function (Card $record, $livewire): ?float {
                        $editionId = $livewire->selectedEditionId ?? null;
                        
                        if ($editionId) {
                            $edition = Edition::find($editionId);
                            if ($edition) {
                                $price = $edition->cardPrices()
                                    ->whereNotNull('market_price')
                                    ->where('sub_type_name', 'like', '%foil%')
                                    ->orderByDesc('updated_at')
                                    ->first();
                                
                                if ($price) {
                                    return $price->market_price;
                                }
                                
                                // Fallback to edition market_price
                                if ($edition->market_price !== null) {
                                    return $edition->market_price;
                                }
                            }
                        }
                        
                        // Fallback to card-level foil price
                        $foilPrice = $record->cardPrices()
                            ->whereNotNull('market_price')
                            ->where('sub_type_name', 'like', '%foil%')
                            ->orderByDesc('updated_at')
                            ->first();
                        
                        return $foilPrice?->market_price;
                    })
                    ->money('USD')
                    ->placeholder('N/A')
                    ->visible(fn ($livewire): bool => ($livewire->viewMode ?? 'list') === 'list'),
                Tables\Columns\TextColumn::make('nonfoil_market_price')
                    ->label('TCGPlayer Market (Non-Foil)')
                    ->state(function (Card $record, $livewire): ?float {
                        $editionId = $livewire->selectedEditionId ?? null;
                        
                        if ($editionId) {
                            $edition = Edition::find($editionId);
                            if ($edition) {
                                $price = $edition->cardPrices()
                                    ->whereNotNull('market_price')
                                    ->where('sub_type_name', 'not like', '%foil%')
                                    ->orderByDesc('updated_at')
                                    ->first();
                                
                                if ($price) {
                                    return $price->market_price;
                                }
                                
                                // Fallback to edition market_price
                                if ($edition->market_price !== null) {
                                    return $edition->market_price;
                                }
                            }
                        }
                        
                        // Fallback to card-level non-foil price
                        $nonFoilPrice = $record->cardPrices()
                            ->whereNotNull('market_price')
                            ->where('sub_type_name', 'not like', '%foil%')
                            ->orderByDesc('updated_at')
                            ->first();
                        
                        return $nonFoilPrice?->market_price;
                    })
                    ->money('USD')
                    ->placeholder('N/A')
                    ->visible(fn ($livewire): bool => ($livewire->viewMode ?? 'list') === 'list'),
                Tables\Columns\TextColumn::make('editions_count')
                    ->label('Editions')
                    ->counts('editions')
                    ->badge()
                    ->color('success')
                    ->sortable()
                    ->visible(fn ($record, $livewire): bool => ($livewire->viewMode ?? 'list') === 'list'),
                Tables\Columns\TextColumn::make('rarity')
                    ->label('Rarity')
                    ->badge()
                    ->formatStateUsing(fn ($state) => match ($state) {
                        1 => 'Common',
                        2 => 'Uncommon',
                        3 => 'Rare',
                        4 => 'Super Rare',
                        default => 'Unknown',
                    })
                    ->color(fn ($state) => match ($state) {
                        1 => 'gray',
                        2 => 'success',
                        3 => 'warning',
                        4 => 'danger',
                        default => 'gray',
                    })
                        ->visible(fn ($record, $livewire): bool => ($livewire->viewMode ?? 'list') === 'list'),
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
                    ->query(fn (Builder $query): Builder => $query->whereHas('cardPrices', fn ($q) => $q->whereNotNull('market_price'))),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Actions\Action::make('add_to_inventory')
                    ->label('Add to Inventory')
                    ->icon('heroicon-o-plus-circle')
                    ->color('success')
                    ->url(fn (Card $record) => \App\Filament\Store\Resources\StoreInventoryResource::getUrl('create', ['card_id' => $record->id]))
                    ->openUrlInNewTab(),
                Actions\Action::make('queue_update')
                    ->label('Queue Update')
                    ->icon('heroicon-o-clipboard-document-list')
                    ->color('primary')
                    ->requiresConfirmation()
                    ->form([
                        Forms\Components\Placeholder::make('card_art')
                            ->label('Card Art')
                            ->content(function (callable $get, Card $record): HtmlString {
                                $editionId = $get('edition_id');
                                $isFoil = (bool) $get('is_foil');
                                $editionImage = $editionId ? Edition::find($editionId)?->image_url : null;
                                $cardImage = $record->image_url;
                                $src = $editionImage ?: $cardImage ?: '/images/card-placeholder.png';

                                $safeSrc = htmlspecialchars($src, ENT_QUOTES, 'UTF-8');
                                $safeAlt = htmlspecialchars($record->name, ENT_QUOTES, 'UTF-8');

                                $shimmerStyle = $isFoil
                                    ? 'position:relative;display:inline-block;overflow:hidden;border-radius:8px;' .
                                        'background:linear-gradient(120deg, rgba(255,255,255,0.15) 0%, rgba(255,255,255,0.35) 30%, rgba(255,255,255,0.05) 60%, rgba(255,255,255,0.25) 100%);' .
                                        'background-size:200% 200%;animation:foil-shimmer 1.8s linear infinite;'
                                    : 'position:relative;display:inline-block;border-radius:8px;';

                                $img = '<div style="' . $shimmerStyle . '"><img src="' . $safeSrc . '" alt="' . $safeAlt . '" style="max-width: 260px; height: auto; display:block; border-radius: 8px;" /></div>';

                                $keyframes = '<style>@keyframes foil-shimmer {0%{background-position:200% 0;}100%{background-position:-200% 0;}}</style>';

                                return new HtmlString($keyframes . $img);
                            })
                            ->reactive()
                            ->columnSpanFull(),
                        Forms\Components\Select::make('edition_id')
                            ->label('Edition')
                            ->required()
                            ->options(function (Card $record) {
                                return $record->editions()
                                    ->orderByDesc('last_update')
                                    ->orderByDesc('created_at')
                                    ->get()
                                    ->mapWithKeys(function (Edition $edition) {
                                        $rarityText = match ($edition->rarity) {
                                            1 => 'Common',
                                            2 => 'Uncommon',
                                            3 => 'Rare',
                                            4 => 'Super Rare',
                                            default => '—',
                                        };

                                        $label = trim(implode(' · ', array_filter([
                                            $edition->slug,
                                            $edition->collector_number,
                                            $rarityText,
                                        ])));

                                        return [$edition->id => $label ?: $edition->id];
                                    });
                            })
                            ->default(fn (Card $record) => $record->editions()->orderByDesc('last_update')->value('id'))
                            ->searchable()
                            ->preload()
                            ->live(),
                        Forms\Components\Placeholder::make('edition_market_price')
                            ->label('Market Price')
                            ->content(function (callable $get, Card $record): string {
                                $editionId = $get('edition_id');
                                $isFoil = (bool) $get('is_foil');
                                $edition = $editionId ? Edition::find($editionId) : null;

                                $price = $edition?->market_price;
                                if ($price !== null) {
                                    return '$' . number_format((float) $price, 2);
                                }

                                // Try latest edition price record, prioritizing foil vs non-foil by sub_type_name
                                $editionPrices = $edition?->cardPrices()
                                    ->whereNotNull('market_price')
                                    ->orderByDesc('updated_at');

                                if ($editionPrices) {
                                    $editionPrice = (clone $editionPrices)
                                        ->when($isFoil, fn ($q) => $q->where('sub_type_name', 'like', '%foil%'))
                                        ->when(! $isFoil, fn ($q) => $q->where('sub_type_name', 'not like', '%foil%'))
                                        ->first() ?? $editionPrices->first();

                                    if ($editionPrice?->market_price !== null) {
                                        return '$' . number_format((float) $editionPrice->market_price, 2);
                                    }
                                }

                                // Fall back to latest card price if edition price is missing
                                $latestPrice = $record->cardPrices()
                                    ->whereNotNull('market_price')
                                    ->orderByDesc('updated_at')
                                    ->first();

                                return $latestPrice?->market_price !== null
                                    ? '$' . number_format((float) $latestPrice->market_price, 2)
                                    : 'N/A';
                            })
                            ->reactive(),
                        Forms\Components\Select::make('location_id')
                            ->label('Location')
                            ->required()
                            ->options(function () {
                                $store = Auth::user()?->currentStore();
                                return $store ? $store->locations()->pluck('name', 'id') : [];
                            })
                            ->default(fn () => Auth::user()?->currentLocation()?->id)
                            ->searchable()
                            ->live(),
                        Forms\Components\Toggle::make('is_foil')
                            ->label('Foil')
                            ->default(false)
                            ->live(),
                        Forms\Components\TextInput::make('delta_quantity')
                            ->label('Quantity Change')
                            ->helperText('Use negative numbers to remove stock')
                            ->numeric()
                            ->required()
                            ->default(0),
                        Forms\Components\TextInput::make('sell_price')
                            ->label('Sell Price')
                            ->numeric()
                            ->prefix('$')
                            ->nullable()
                            ->default(function (callable $get, Card $record) {
                                $locationId = $get('location_id') ?? Auth::user()?->currentLocation()?->id;
                                $editionId = $get('edition_id');
                                $isFoil = (bool) $get('is_foil');

                                if (! $locationId || ! $editionId) {
                                    return null;
                                }

                                $inv = Inventory::where('card_id', $record->id)
                                    ->where('edition_id', $editionId)
                                    ->where('location_id', $locationId)
                                    ->where('is_foil', $isFoil)
                                    ->first();
                                return $inv?->sell_price;
                            })
                            ->reactive()
                            ->afterStateHydrated(function (callable $set, callable $get, Card $record, $state) {
                                if ($state !== null) {
                                    return;
                                }

                                $locationId = $get('location_id') ?? Auth::user()?->currentLocation()?->id;
                                $editionId = $get('edition_id');
                                $isFoil = (bool) $get('is_foil');

                                if (! $locationId || ! $editionId) {
                                    return;
                                }

                                $inv = Inventory::where('card_id', $record->id)
                                    ->where('edition_id', $editionId)
                                    ->where('location_id', $locationId)
                                    ->where('is_foil', $isFoil)
                                    ->first();

                                if ($inv?->sell_price !== null) {
                                    $set('sell_price', $inv->sell_price);
                                }
                            }),
                    ])
                    ->action(function (Card $record, array $data) {
                        InventoryUpdateQueue::add(
                            $record->id,
                            $data['edition_id'],
                            (int) $data['location_id'],
                            (int) $data['delta_quantity'],
                            $data['sell_price'] !== '' ? (float) $data['sell_price'] : null,
                            (bool) ($data['is_foil'] ?? false)
                        );

                        Notification::make()
                            ->title('Queued update')
                            ->body("{$record->name} queued for inventory update")
                            ->success()
                            ->send();
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkAction::make('update_inventory')
                    ->label('Update Inventory')
                    ->icon('heroicon-o-clipboard-document-list')
                    ->color('primary')
                    ->requiresConfirmation()
                    ->action(function (Collection $records) {
                        $cardIds = $records->pluck('id')->all();
                        Session::put('bulk_inventory_card_ids', $cardIds);

                        return redirect(\App\Filament\Store\Pages\BulkInventoryCreate::getUrl());
                    })
                    ->deselectRecordsAfterCompletion(),
            ])
            ->paginationPageOptions([24, 48, 96, 192])
            ->defaultPaginationPageOption(48);
    }

    public static function getRelations(): array
    {
        return [
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

