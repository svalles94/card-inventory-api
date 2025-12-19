<?php

namespace App\Filament\Resources\CardResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Models\Set;

class EditionsRelationManager extends RelationManager
{
    protected static string $relationship = 'editions';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Edition Information')
                    ->schema([
                        Forms\Components\TextInput::make('id')
                            ->label('Edition ID')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\Select::make('set_id')
                            ->label('Set')
                            ->relationship('set', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),
                        Forms\Components\TextInput::make('collector_number')
                            ->maxLength(255),
                        Forms\Components\TextInput::make('configuration')
                            ->maxLength(255),
                        Forms\Components\TextInput::make('slug')
                            ->maxLength(255),
                    ])
                    ->columns(2),
                
                Forms\Components\Section::make('Edition Text')
                    ->schema([
                        Forms\Components\Textarea::make('effect')
                            ->rows(3)
                            ->columnSpanFull(),
                        Forms\Components\Textarea::make('effect_html')
                            ->rows(3)
                            ->columnSpanFull(),
                        Forms\Components\Textarea::make('effect_raw')
                            ->rows(3)
                            ->columnSpanFull(),
                        Forms\Components\Textarea::make('flavor')
                            ->rows(2)
                            ->columnSpanFull(),
                        Forms\Components\TextInput::make('illustrator')
                            ->maxLength(255),
                    ]),
                
                Forms\Components\Section::make('Edition Details')
                    ->schema([
                        Forms\Components\TextInput::make('image')
                            ->url()
                            ->columnSpanFull(),
                        Forms\Components\TextInput::make('rarity')
                            ->numeric(),
                        Forms\Components\TextInput::make('orientation')
                            ->maxLength(255),
                        Forms\Components\TagsInput::make('other_orientations')
                            ->placeholder('Add orientation'),
                    ])
                    ->columns(2),
                
                Forms\Components\Section::make('TCGPlayer Integration')
                    ->schema([
                        Forms\Components\TextInput::make('tcgplayer_product_id')
                            ->numeric(),
                        Forms\Components\TextInput::make('tcgplayer_sku')
                            ->maxLength(255),
                        Forms\Components\TextInput::make('market_price')
                            ->numeric()
                            ->prefix('$'),
                        Forms\Components\TextInput::make('tcgplayer_low_price')
                            ->numeric()
                            ->prefix('$'),
                        Forms\Components\TextInput::make('tcgplayer_high_price')
                            ->numeric()
                            ->prefix('$'),
                        Forms\Components\DateTimePicker::make('last_price_update'),
                    ])
                    ->columns(3),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('collector_number')
            ->columns([
                Tables\Columns\TextColumn::make('set.name')
                    ->label('Set')
                    ->searchable()
                    ->sortable()
                    ->size(Tables\Columns\TextColumn\TextColumnSize::Small)
                    ->limit(20),
                Tables\Columns\TextColumn::make('collector_number')
                    ->label('#')
                    ->searchable()
                    ->sortable()
                    ->size(Tables\Columns\TextColumn\TextColumnSize::ExtraSmall)
                    ->alignCenter(),
                Tables\Columns\TextColumn::make('configuration')
                    ->searchable()
                    ->toggleable()
                    ->size(Tables\Columns\TextColumn\TextColumnSize::ExtraSmall),
                Tables\Columns\TextColumn::make('rarity')
                    ->numeric()
                    ->sortable()
                    ->size(Tables\Columns\TextColumn\TextColumnSize::ExtraSmall)
                    ->alignCenter(),
                Tables\Columns\ImageColumn::make('image')
                    ->circular()
                    ->size(40)
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('market_price')
                    ->money('USD')
                    ->sortable()
                    ->toggleable()
                    ->size(Tables\Columns\TextColumn\TextColumnSize::ExtraSmall)
                    ->alignEnd(),
                Tables\Columns\TextColumn::make('tcgplayer_low_price')
                    ->money('USD')
                    ->label('Low')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->size(Tables\Columns\TextColumn\TextColumnSize::ExtraSmall)
                    ->alignEnd(),
                Tables\Columns\TextColumn::make('tcgplayer_high_price')
                    ->money('USD')
                    ->label('High')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->size(Tables\Columns\TextColumn\TextColumnSize::ExtraSmall)
                    ->alignEnd(),
                Tables\Columns\TextColumn::make('last_price_update')
                    ->dateTime('M d, Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->size(Tables\Columns\TextColumn\TextColumnSize::ExtraSmall),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime('M d, Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->size(Tables\Columns\TextColumn\TextColumnSize::ExtraSmall),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('set_id')
                    ->label('Set')
                    ->relationship('set', 'name')
                    ->searchable()
                    ->preload(),
                Tables\Filters\Filter::make('has_pricing')
                    ->label('Has Pricing')
                    ->query(fn (Builder $query): Builder => $query->whereNotNull('market_price')),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->iconButton(),
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
            ->defaultSort('set.name')
            ->striped()
            ->paginated([10, 25, 50, 100]);
    }
}

