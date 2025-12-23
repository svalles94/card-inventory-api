<?php

namespace App\Filament\Resources\CardResource\Pages;

use App\Filament\Resources\CardResource;
use Filament\Actions;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;

class ViewCard extends ViewRecord
{
    protected static string $resource = CardResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make('Pricing Information')
                    ->schema([
                        TextEntry::make('latest_market_price')
                            ->label('Latest Market Price')
                            ->state(function ($record) {
                                $latestPrice = $record->cardPrices()
                                    ->whereNotNull('market_price')
                                    ->orderBy('last_updated', 'desc')
                                    ->first();
                                return $latestPrice ? '$' . number_format($latestPrice->market_price, 2) : 'No price data';
                            })
                            ->badge()
                            ->color('success'),
                        TextEntry::make('price_range')
                            ->label('Price Range')
                            ->state(function ($record) {
                                $prices = $record->cardPrices()
                                    ->whereNotNull('market_price')
                                    ->get();
                                
                                if ($prices->isEmpty()) {
                                    return 'No price data';
                                }
                                
                                $min = $prices->min('market_price');
                                $max = $prices->max('market_price');
                                
                                if ($min == $max) {
                                    return '$' . number_format($min, 2);
                                }
                                
                                return '$' . number_format($min, 2) . ' - $' . number_format($max, 2);
                            }),
                        TextEntry::make('price_count')
                            ->label('Total Price Records')
                            ->state(fn ($record) => $record->cardPrices()->count())
                            ->badge()
                            ->color('info'),
                    ])
                    ->columns(3)
                    ->collapsible(),
                
                Section::make('Basic Information')
                    ->schema([
                        TextEntry::make('id')
                            ->label('Card ID'),
                        TextEntry::make('name'),
                        TextEntry::make('slug'),
                        TextEntry::make('image')
                            ->url(fn ($record) => $record->image)
                            ->openUrlInNewTab(),
                        TextEntry::make('image_filename'),
                    ])
                    ->columns(3),
                
                Section::make('Card Stats')
                    ->schema([
                        TextEntry::make('cost_memory')
                            ->label('Memory')
                            ->numeric(),
                        TextEntry::make('cost_reserve')
                            ->label('Reserve')
                            ->numeric(),
                        TextEntry::make('durability')
                            ->numeric(),
                        TextEntry::make('power')
                            ->numeric(),
                        TextEntry::make('life')
                            ->numeric(),
                        TextEntry::make('level')
                            ->numeric(),
                        TextEntry::make('speed')
                            ->numeric(),
                        TextEntry::make('rarity')
                            ->numeric(),
                    ])
                    ->columns(8),
                
                Section::make('Card Text')
                    ->schema([
                        TextEntry::make('effect')
                            ->columnSpanFull(),
                        TextEntry::make('effect_html')
                            ->columnSpanFull()
                            ->html(),
                        TextEntry::make('effect_raw')
                            ->columnSpanFull(),
                        TextEntry::make('flavor')
                            ->columnSpanFull(),
                        TextEntry::make('illustrator'),
                    ])
                    ->collapsible(),
                
                Section::make('Card Attributes')
                    ->schema([
                        TextEntry::make('types')
                            ->badge()
                            ->separator(','),
                        TextEntry::make('subtypes')
                            ->badge()
                            ->separator(','),
                        TextEntry::make('classes')
                            ->badge()
                            ->separator(','),
                        TextEntry::make('elements')
                            ->badge()
                            ->separator(','),
                    ])
                    ->columns(4)
                    ->collapsible(),
                
                Section::make('Metadata')
                    ->schema([
                        TextEntry::make('legality'),
                        TextEntry::make('created_at')
                            ->dateTime(),
                        TextEntry::make('last_update')
                            ->dateTime(),
                    ])
                    ->columns(3)
                    ->collapsible()
                    ->collapsed(),
            ]);
    }
}
