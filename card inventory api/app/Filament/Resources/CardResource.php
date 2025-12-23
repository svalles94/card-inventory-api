<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CardResource\Pages;
use App\Filament\Resources\CardResource\RelationManagers;
use App\Models\Card;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class CardResource extends Resource
{
    protected static ?string $model = Card::class;

    protected static ?string $navigationIcon = 'heroicon-o-credit-card';
    
    protected static ?string $navigationGroup = 'Card Management';
    
    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Basic Information')
                    ->collapsible()
                    ->schema([
                        Forms\Components\TextInput::make('id')
                            ->label('Card ID')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('slug')
                            ->maxLength(255),
                        Forms\Components\TextInput::make('image')
                            ->url()
                            ->columnSpanFull(),
                        Forms\Components\TextInput::make('image_filename')
                            ->maxLength(255),
                    ])
                    ->columns(3),
                
                Forms\Components\Section::make('Card Stats')
                    ->schema([
                        Forms\Components\TextInput::make('cost_memory')
                            ->label('Memory')
                            ->numeric(),
                        Forms\Components\TextInput::make('cost_reserve')
                            ->label('Reserve')
                            ->numeric(),
                        Forms\Components\TextInput::make('durability')
                            ->numeric(),
                        Forms\Components\TextInput::make('power')
                            ->numeric(),
                        Forms\Components\TextInput::make('life')
                            ->numeric(),
                        Forms\Components\TextInput::make('level')
                            ->numeric(),
                        Forms\Components\TextInput::make('speed')
                            ->numeric(),
                        Forms\Components\TextInput::make('rarity')
                            ->numeric(),
                    ])
                    ->columns(8),
                
                Forms\Components\Section::make('Card Text')
                    ->collapsible()
                    ->schema([
                        Forms\Components\Textarea::make('effect')
                            ->rows(2)
                            ->columnSpanFull(),
                        Forms\Components\Textarea::make('effect_html')
                            ->rows(2)
                            ->columnSpanFull(),
                        Forms\Components\Textarea::make('effect_raw')
                            ->rows(2)
                            ->columnSpanFull(),
                        Forms\Components\Textarea::make('flavor')
                            ->rows(1)
                            ->columnSpanFull(),
                        Forms\Components\TextInput::make('illustrator')
                            ->maxLength(255),
                    ])
                    ->columns(1),
                
                Forms\Components\Section::make('Card Attributes')
                    ->collapsible()
                    ->schema([
                        Forms\Components\TagsInput::make('types')
                            ->placeholder('Add type'),
                        Forms\Components\TagsInput::make('subtypes')
                            ->placeholder('Add subtype'),
                        Forms\Components\TagsInput::make('classes')
                            ->placeholder('Add class'),
                        Forms\Components\TagsInput::make('elements')
                            ->placeholder('Add element'),
                    ])
                    ->columns(4),
                
                Forms\Components\Section::make('References')
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        Forms\Components\TagsInput::make('referenced_by')
                            ->placeholder('Add referenced by'),
                        Forms\Components\TagsInput::make('references')
                            ->placeholder('Add reference'),
                        Forms\Components\KeyValue::make('rule')
                            ->keyLabel('Key')
                            ->valueLabel('Value'),
                    ])
                    ->columns(1),
                
                Forms\Components\Section::make('Pricing')
                    ->collapsible()
                    ->schema([
                        Forms\Components\TextInput::make('latest_market_price')
                            ->label('Latest Market Price')
                            ->disabled()
                            ->dehydrated(false)
                            ->formatStateUsing(function ($record) {
                                if (!$record) return 'N/A';
                                $latestPrice = $record->cardPrices()
                                    ->whereNotNull('market_price')
                                    ->orderBy('last_updated', 'desc')
                                    ->first();
                                return $latestPrice ? '$' . number_format($latestPrice->market_price, 2) : 'No price data';
                            }),
                        Forms\Components\TextInput::make('price_count')
                            ->label('Price Records')
                            ->disabled()
                            ->dehydrated(false)
                            ->formatStateUsing(function ($record) {
                                if (!$record) return '0';
                                return $record->cardPrices()->count();
                            }),
                    ])
                    ->columns(2),
                
                Forms\Components\Section::make('Metadata')
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        Forms\Components\TextInput::make('legality')
                            ->maxLength(255),
                        Forms\Components\DateTimePicker::make('created_at'),
                        Forms\Components\DateTimePicker::make('last_update'),
                    ])
                    ->columns(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->searchable()
                    ->sortable()
                    ->size(Tables\Columns\TextColumn\TextColumnSize::ExtraSmall),
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->limit(30)
                    ->size(Tables\Columns\TextColumn\TextColumnSize::Small)
                    ->weight('medium'),
                Tables\Columns\TextColumn::make('elements')
                    ->badge()
                    ->formatStateUsing(fn ($state) => is_array($state) ? implode(', ', array_slice($state, 0, 3)) . (count($state) > 3 ? '...' : '') : $state)
                    ->color('info')
                    ->size(Tables\Columns\TextColumn\TextColumnSize::ExtraSmall),
                Tables\Columns\TextColumn::make('classes')
                    ->badge()
                    ->formatStateUsing(fn ($state) => is_array($state) ? implode(', ', array_slice($state, 0, 3)) . (count($state) > 3 ? '...' : '') : $state)
                    ->color('success')
                    ->size(Tables\Columns\TextColumn\TextColumnSize::ExtraSmall),
                Tables\Columns\TextColumn::make('market_price')
                    ->label('Market Price')
                    ->money('USD')
                    ->sortable()
                    ->size(Tables\Columns\TextColumn\TextColumnSize::ExtraSmall)
                    ->alignEnd()
                    ->state(function ($record) {
                        $latestPrice = $record->cardPrices()
                            ->whereNotNull('market_price')
                            ->orderBy('last_updated', 'desc')
                            ->first();
                        return $latestPrice?->market_price;
                    }),
                Tables\Columns\TextColumn::make('editions_count')
                    ->label('Editions')
                    ->counts('editions')
                    ->badge()
                    ->color('info')
                    ->sortable()
                    ->size(Tables\Columns\TextColumn\TextColumnSize::ExtraSmall)
                    ->alignCenter(),
            ])
            ->filters([
                Tables\Filters\Filter::make('rarity')
                    ->form([
                        Forms\Components\TextInput::make('rarity_from')
                            ->label('Rarity From')
                            ->numeric(),
                        Forms\Components\TextInput::make('rarity_to')
                            ->label('Rarity To')
                            ->numeric(),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['rarity_from'],
                                fn (Builder $query, $value): Builder => $query->where('rarity', '>=', $value),
                            )
                            ->when(
                                $data['rarity_to'],
                                fn (Builder $query, $value): Builder => $query->where('rarity', '<=', $value),
                            );
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->iconButton(),
                Tables\Actions\EditAction::make()
                    ->iconButton(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('name')
            ->striped()
            ->paginated([10, 25, 50, 100]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\EditionsRelationManager::class,
            RelationManagers\PricesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCards::route('/'),
            'create' => Pages\CreateCard::route('/create'),
            'view' => Pages\ViewCard::route('/{record}'),
            'edit' => Pages\EditCard::route('/{record}/edit'),
        ];
    }
}
