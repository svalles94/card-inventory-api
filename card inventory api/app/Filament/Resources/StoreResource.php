<?php

namespace App\Filament\Resources;

use App\Filament\Resources\StoreResource\Pages;
use App\Filament\Resources\StoreResource\RelationManagers;
use App\Models\Store;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class StoreResource extends Resource
{
    protected static ?string $model = Store::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-storefront';
    
    protected static ?string $navigationGroup = 'Stores & Locations';
    
    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Store Information')
                    ->schema([
                        Forms\Components\Select::make('owner_id')
                            ->relationship('owner', 'name')
                            ->required()
                            ->searchable()
                            ->preload()
                            ->label('Store Owner'),
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->label('Store Name'),
                    ])->columns(2),
                    
                Forms\Components\Section::make('Contact Information')
                    ->schema([
                        Forms\Components\TextInput::make('address')
                            ->maxLength(255)
                            ->columnSpanFull(),
                        Forms\Components\TextInput::make('phone')
                            ->tel()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('email')
                            ->email()
                            ->maxLength(255),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Store Name')
                    ->searchable()
                    ->sortable()
                    ->size('lg')
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('owner.name')
                    ->label('Owner')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('locations_count')
                    ->counts('locations')
                    ->label('Locations')
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
                Tables\Columns\TextColumn::make('email')
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
            'index' => Pages\ListStores::route('/'),
            'create' => Pages\CreateStore::route('/create'),
            'edit' => Pages\EditStore::route('/{record}/edit'),
        ];
    }
}
