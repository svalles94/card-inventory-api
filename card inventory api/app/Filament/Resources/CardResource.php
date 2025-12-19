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

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Basic Information')
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
                    ->columns(2),
                
                Forms\Components\Section::make('Card Stats')
                    ->schema([
                        Forms\Components\TextInput::make('cost_memory')
                            ->numeric(),
                        Forms\Components\TextInput::make('cost_reserve')
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
                    ->columns(4),
                
                Forms\Components\Section::make('Card Text')
                    ->schema([
                        Forms\Components\Textarea::make('effect')
                            ->rows(3)
                            ->columnSpanFull(),
                        Forms\Components\Textarea::make('effect_raw')
                            ->rows(3)
                            ->columnSpanFull(),
                        Forms\Components\Textarea::make('effect_html')
                            ->rows(3)
                            ->columnSpanFull(),
                        Forms\Components\Textarea::make('flavor')
                            ->rows(2)
                            ->columnSpanFull(),
                        Forms\Components\TextInput::make('illustrator')
                            ->maxLength(255),
                    ]),
                
                Forms\Components\Section::make('Card Attributes')
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
                    ->columns(2),
                
                Forms\Components\Section::make('References')
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
                
                Forms\Components\Section::make('Metadata')
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
                    ->limit(25)
                    ->size(Tables\Columns\TextColumn\TextColumnSize::Small)
                    ->weight('medium'),
                Tables\Columns\TextColumn::make('elements')
                    ->badge()
                    ->formatStateUsing(fn ($state) => is_array($state) ? implode(', ', array_slice($state, 0, 2)) . (count($state) > 2 ? '...' : '') : $state)
                    ->color('info')
                    ->size(Tables\Columns\TextColumn\TextColumnSize::ExtraSmall),
                Tables\Columns\TextColumn::make('classes')
                    ->badge()
                    ->formatStateUsing(fn ($state) => is_array($state) ? implode(', ', array_slice($state, 0, 2)) . (count($state) > 2 ? '...' : '') : $state)
                    ->color('success')
                    ->size(Tables\Columns\TextColumn\TextColumnSize::ExtraSmall),
                Tables\Columns\TextColumn::make('cost_memory')
                    ->label('M')
                    ->numeric()
                    ->sortable()
                    ->size(Tables\Columns\TextColumn\TextColumnSize::ExtraSmall)
                    ->alignCenter(),
                Tables\Columns\TextColumn::make('cost_reserve')
                    ->label('R')
                    ->numeric()
                    ->sortable()
                    ->size(Tables\Columns\TextColumn\TextColumnSize::ExtraSmall)
                    ->alignCenter(),
                Tables\Columns\TextColumn::make('power')
                    ->label('P')
                    ->numeric()
                    ->sortable()
                    ->size(Tables\Columns\TextColumn\TextColumnSize::ExtraSmall)
                    ->alignCenter(),
                Tables\Columns\TextColumn::make('rarity')
                    ->label('Rarity')
                    ->numeric()
                    ->sortable()
                    ->size(Tables\Columns\TextColumn\TextColumnSize::ExtraSmall)
                    ->alignCenter(),
                Tables\Columns\TextColumn::make('editions_count')
                    ->label('Ed.')
                    ->counts('editions')
                    ->badge()
                    ->color('info')
                    ->sortable()
                    ->size(Tables\Columns\TextColumn\TextColumnSize::ExtraSmall)
                    ->alignCenter(),
                Tables\Columns\TextColumn::make('last_update')
                    ->dateTime('M d, Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->size(Tables\Columns\TextColumn\TextColumnSize::ExtraSmall),
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
