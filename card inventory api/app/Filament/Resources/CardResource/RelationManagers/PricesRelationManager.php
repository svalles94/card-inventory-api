<?php

namespace App\Filament\Resources\CardResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class PricesRelationManager extends RelationManager
{
    protected static string $relationship = 'cardPrices';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('tcgplayer_product_id')
                    ->label('TCGPlayer Product ID')
                    ->numeric()
                    ->required(),
                Forms\Components\TextInput::make('sub_type_name')
                    ->label('Sub Type')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('market_price')
                    ->label('Market Price')
                    ->numeric()
                    ->prefix('$')
                    ->step(0.01),
                Forms\Components\TextInput::make('low_price')
                    ->label('Low Price')
                    ->numeric()
                    ->prefix('$')
                    ->step(0.01),
                Forms\Components\TextInput::make('high_price')
                    ->label('High Price')
                    ->numeric()
                    ->prefix('$')
                    ->step(0.01),
                Forms\Components\DateTimePicker::make('last_updated')
                    ->label('Last Updated'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('tcgplayer_product_id')
            ->columns([
                Tables\Columns\TextColumn::make('edition.collector_number')
                    ->label('Edition')
                    ->sortable()
                    ->searchable()
                    ->size(Tables\Columns\TextColumn\TextColumnSize::ExtraSmall),
                Tables\Columns\TextColumn::make('sub_type_name')
                    ->label('Type')
                    ->badge()
                    ->color('info')
                    ->size(Tables\Columns\TextColumn\TextColumnSize::ExtraSmall)
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('tcgplayer_product_id')
                    ->label('TCGPlayer ID')
                    ->numeric()
                    ->sortable()
                    ->size(Tables\Columns\TextColumn\TextColumnSize::ExtraSmall),
                Tables\Columns\TextColumn::make('market_price')
                    ->label('Market')
                    ->money('USD')
                    ->sortable()
                    ->size(Tables\Columns\TextColumn\TextColumnSize::ExtraSmall)
                    ->alignEnd(),
                Tables\Columns\TextColumn::make('low_price')
                    ->label('Low')
                    ->money('USD')
                    ->sortable()
                    ->size(Tables\Columns\TextColumn\TextColumnSize::ExtraSmall)
                    ->alignEnd(),
                Tables\Columns\TextColumn::make('high_price')
                    ->label('High')
                    ->money('USD')
                    ->sortable()
                    ->size(Tables\Columns\TextColumn\TextColumnSize::ExtraSmall)
                    ->alignEnd(),
                Tables\Columns\TextColumn::make('last_updated')
                    ->label('Updated')
                    ->dateTime('M d, Y H:i')
                    ->sortable()
                    ->size(Tables\Columns\TextColumn\TextColumnSize::ExtraSmall),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->iconButton(),
                Tables\Actions\DeleteAction::make()
                    ->iconButton(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('last_updated', 'desc')
            ->striped()
            ->paginated([10, 25, 50]);
    }
}

