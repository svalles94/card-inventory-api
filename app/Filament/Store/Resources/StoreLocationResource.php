<?php

namespace App\Filament\Store\Resources;

use App\Filament\Store\Resources\StoreLocationResource\Pages;
use App\Models\Location;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class StoreLocationResource extends Resource
{
    protected static ?string $model = Location::class;

    protected static ?string $navigationIcon = 'heroicon-o-map-pin';
    
    protected static ?string $navigationGroup = 'Locations';
    
    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        $currentStore = auth()->user()->currentStore();
        
        if (!$currentStore) {
            return $form->schema([]);
        }
        
        return $form
            ->schema([
                Forms\Components\Section::make('Location Information')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->label('Location Name')
                            ->placeholder('e.g., Main Store, Warehouse, Online')
                            ->helperText('A name to identify this location'),
                        Forms\Components\Textarea::make('address')
                            ->rows(3)
                            ->maxLength(500)
                            ->label('Address')
                            ->placeholder('Street address, city, state, zip')
                            ->columnSpanFull(),
                        Forms\Components\TextInput::make('phone')
                            ->tel()
                            ->maxLength(255)
                            ->label('Phone Number')
                            ->placeholder('(555) 123-4567'),
                    ])
                    ->columns(2),
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
                // Only show locations for the current store
                return $query->where('store_id', $currentStore->id);
            })
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Location Name')
                    ->searchable()
                    ->sortable()
                    ->size('lg')
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('address')
                    ->searchable()
                    ->wrap()
                    ->toggleable()
                    ->limit(50),
                Tables\Columns\TextColumn::make('phone')
                    ->searchable()
                    ->copyable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('inventory_count')
                    ->label('Inventory Items')
                    ->counts('inventory')
                    ->badge()
                    ->color('info')
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('M j, Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('name')
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('No locations yet')
            ->emptyStateDescription('Create your first location to start managing inventory.')
            ->emptyStateIcon('heroicon-o-map-pin');
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
            'index' => Pages\ListStoreLocations::route('/'),
            'create' => Pages\CreateStoreLocation::route('/create'),
            'edit' => Pages\EditStoreLocation::route('/{record}/edit'),
        ];
    }
}

