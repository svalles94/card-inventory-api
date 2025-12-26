<?php

namespace App\Filament\Store\Resources\StoreCardResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use App\Models\Set;

class EditionsRelationManager extends RelationManager
{
    protected static string $relationship = 'editions';

    // Read-only for store owners
    protected static bool $isLazy = false;

    public function form(Form $form): Form
    {
        // Read-only form for store owners
        return $form
            ->schema([
                Forms\Components\Section::make('Edition Information')
                    ->schema([
                        Forms\Components\Placeholder::make('id')
                            ->label('Edition ID')
                            ->content(fn ($record) => $record->id ?? 'N/A'),
                        Forms\Components\Placeholder::make('set.name')
                            ->label('Set')
                            ->content(fn ($record) => $record->set->name ?? 'N/A'),
                        Forms\Components\Placeholder::make('collector_number')
                            ->label('Collector Number')
                            ->content(fn ($record) => $record->collector_number ?? 'N/A'),
                        Forms\Components\Placeholder::make('configuration')
                            ->label('Configuration')
                            ->content(fn ($record) => $record->configuration ?? 'N/A'),
                    ])
                    ->columns(2),
                
                Forms\Components\Section::make('Pricing')
                    ->schema([
                        Forms\Components\Placeholder::make('market_price')
                            ->label('Market Price')
                            ->content(function ($record) {
                                $price = $record->cardPrices()
                                    ->whereNotNull('market_price')
                                    ->orderBy('updated_at', 'desc')
                                    ->first();
                                return $price ? '$' . number_format($price->market_price, 2) : 'N/A';
                            }),
                        Forms\Components\Placeholder::make('rarity')
                            ->label('Rarity')
                            ->content(fn ($record) => match($record->rarity ?? null) {
                                1 => 'Common',
                                2 => 'Uncommon',
                                3 => 'Rare',
                                4 => 'Super Rare',
                                default => 'N/A',
                            }),
                    ])
                    ->columns(2),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('collector_number')
            ->columns([
                Tables\Columns\ImageColumn::make('image_url')
                    ->label('Image')
                    ->height(120)
                    ->width(80)
                    ->defaultImageUrl('/images/card-placeholder.png')
                    ->url(fn ($record): ?string => $record->image_url)
                    ->openUrlInNewTab()
                    ->visibleFrom('md'),

                Tables\Columns\TextColumn::make('set.name')
                    ->label('Set')
                    ->searchable()
                    ->sortable()
                    ->size('sm')
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('collector_number')
                    ->label('Collector #')
                    ->searchable()
                    ->sortable()
                    ->size('sm'),

                Tables\Columns\TextColumn::make('configuration')
                    ->label('Config')
                    ->size('sm')
                    ->toggleable()
                    ->visibleFrom('lg'),

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
                    ->size('sm'),

                Tables\Columns\TextColumn::make('market_price')
                    ->label('Market Price')
                    ->state(function ($record) {
                        $price = $record->cardPrices()
                            ->whereNotNull('market_price')
                            ->orderBy('updated_at', 'desc')
                            ->first();
                        return $price?->market_price;
                    })
                    ->money('USD')
                    ->sortable()
                    ->size('sm')
                    ->alignEnd(),
            ])
            ->defaultSort('collector_number')
            ->striped()
            ->paginated([10, 25, 50])
            ->actions([
                // Read-only, no actions
            ])
            ->bulkActions([
                // Read-only, no bulk actions
            ]);
    }
}

