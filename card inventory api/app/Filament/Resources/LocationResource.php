<?php

namespace App\Filament\Resources;

use App\Filament\Resources\LocationResource\Pages;
use App\Filament\Resources\LocationResource\RelationManagers;
use App\Models\Location;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class LocationResource extends Resource
{
    protected static ?string $model = Location::class;

    protected static ?string $navigationIcon = 'heroicon-o-map-pin';
    
    protected static ?string $navigationGroup = 'Stores & Locations';
    
    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Location Details')
                    ->schema([
                        Forms\Components\Select::make('store_id')
                            ->relationship('store', 'name')
                            ->required()
                            ->searchable()
                            ->preload()
                            ->label('Store'),
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->label('Location Name')
                            ->helperText('e.g., Main Store, Warehouse, Counter Display'),
                    ])->columns(2),
                    
                Forms\Components\Section::make('Contact Information')
                    ->schema([
                        Forms\Components\TextInput::make('address')
                            ->maxLength(255)
                            ->columnSpanFull(),
                        Forms\Components\TextInput::make('phone')
                            ->tel()
                            ->maxLength(255),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Location Name')
                    ->searchable()
                    ->sortable()
                    ->size('lg')
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('store.name')
                    ->label('Store')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('inventory_count')
                    ->counts('inventory')
                    ->label('Items')
                    ->badge()
                    ->color('success'),
                Tables\Columns\TextColumn::make('inventory_sum_quantity')
                    ->sum('inventory', 'quantity')
                    ->label('Total Cards')
                    ->badge()
                    ->color('info'),
                Tables\Columns\TextColumn::make('address')
                    ->searchable()
                    ->wrap()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('phone')
                    ->searchable()
                    ->copyable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('M j, Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('store')
                    ->relationship('store', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
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
            'index' => Pages\ListLocations::route('/'),
            'create' => Pages\CreateLocation::route('/create'),
            'edit' => Pages\EditLocation::route('/{record}/edit'),
        ];
    }
}
