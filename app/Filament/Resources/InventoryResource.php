<?php

namespace App\Filament\Resources;

use App\Filament\Resources\InventoryResource\Pages;
use App\Filament\Resources\InventoryResource\RelationManagers;
use App\Models\Inventory;
use App\Models\Card;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Support\Colors\Color;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class InventoryResource extends Resource
{
    protected static ?string $model = Inventory::class;

    protected static ?string $navigationIcon = 'heroicon-o-cube';
    
    protected static ?string $navigationGroup = 'Inventory';
    
    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Card Selection')
                    ->schema([
                        Forms\Components\Select::make('location_id')
                            ->relationship('location', 'name')
                            ->required()
                            ->searchable()
                            ->preload()
                            ->label('Location'),
                        Forms\Components\Select::make('card_id')
                            ->relationship('card', 'name')
                            ->required()
                            ->searchable()
                            ->preload()
                            ->getOptionLabelFromRecordUsing(fn (Card $record) => "{$record->name} ({$record->set_code} - {$record->card_number})")
                            ->label('Card'),
                    ])->columns(2),
                    
                Forms\Components\Section::make('Quantity')
                    ->schema([
                        Forms\Components\TextInput::make('quantity')
                            ->required()
                            ->numeric()
                            ->default(0)
                            ->minValue(0)
                            ->label('Stock Quantity')
                            ->helperText('Current number of cards in stock')
                            ->extraInputAttributes(['style' => 'font-size: 1.5rem; font-weight: bold;']),
                    ]),
                    
                Forms\Components\Section::make('Pricing')
                    ->schema([
                        Forms\Components\TextInput::make('buy_price')
                            ->numeric()
                            ->prefix('$')
                            ->label('Buy Price')
                            ->helperText('What you paid for it'),
                        Forms\Components\TextInput::make('sell_price')
                            ->numeric()
                            ->prefix('$')
                            ->label('Sell Price')
                            ->helperText('What you sell it for'),
                        Forms\Components\TextInput::make('market_price')
                            ->numeric()
                            ->prefix('$')
                            ->label('Market Price')
                            ->helperText('Current market value'),
                    ])->columns(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('card.image')
                    ->label('Image')
                    ->size(80)
                    ->defaultImageUrl('/images/card-placeholder.png')
                    ->visibleFrom('md'),
                    
                Tables\Columns\Layout\Stack::make([
                    Tables\Columns\TextColumn::make('card.name')
                        ->label('Card Name')
                        ->searchable()
                        ->sortable()
                        ->size('lg')
                        ->weight('bold')
                        ->wrap(),
                        
                    Tables\Columns\Layout\Stack::make([
                        Tables\Columns\TextColumn::make('card.set_code')
                            ->badge()
                            ->color('info')
                            ->size('sm'),
                        Tables\Columns\TextColumn::make('card.card_number')
                            ->prefix('#')
                            ->size('sm')
                            ->color('gray'),
                    ])->space(1),
                ])->space(1),
                    
                Tables\Columns\TextColumn::make('card.rarity')
                    ->label('Rarity')
                    ->badge()
                    ->color(fn ($state) => match($state) {
                        1 => 'gray',
                        2 => 'success', 
                        3 => 'warning',
                        4 => 'danger',
                        default => 'gray',
                    })
                    ->visibleFrom('lg'),
                    
                Tables\Columns\TextColumn::make('location.name')
                    ->label('Location')
                    ->searchable()
                    ->sortable()
                    ->toggleable()
                    ->visibleFrom('lg'),
                    
                Tables\Columns\TextColumn::make('quantity')
                    ->label('On Hand')
                    ->sortable()
                    ->size('xl')
                    ->weight('bold')
                    ->color(fn ($state) => match(true) {
                        $state === 0 => 'danger',
                        $state < 4 => 'warning',
                        $state < 10 => 'info',
                        default => 'success',
                    })
                    ->badge(),
                    
                Tables\Columns\TextColumn::make('sell_price')
                    ->label('Price')
                    ->money('USD')
                    ->sortable()
                    ->toggleable()
                    ->visibleFrom('md'),
                    
                Tables\Columns\TextColumn::make('total_value')
                    ->label('Total Value')
                    ->state(fn (Inventory $record): float => ($record->sell_price ?? 0) * $record->quantity)
                    ->money('USD')
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query->orderByRaw("(sell_price * quantity) {$direction}");
                    })
                    ->toggleable()
                    ->visibleFrom('lg'),
                    
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Last Updated')
                    ->dateTime('M j, g:i A')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->contentGrid([
                'md' => 2,
                'lg' => 3,
                'xl' => 4,
            ])
            ->defaultSort('card.name')
            ->filters([
                Tables\Filters\SelectFilter::make('location')
                    ->relationship('location', 'name')
                    ->label('Location')
                    ->searchable()
                    ->preload(),
                    
                Tables\Filters\SelectFilter::make('game')
                    ->label('Game')
                    ->options([
                        'grand-archive' => 'Grand Archive',
                        'gundam' => 'Gundam',
                        'riftbound' => 'Riftbound',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            $data['value'],
                            fn (Builder $query, $value): Builder => $query->whereHas('card', fn ($q) => $q->where('game', $value))
                        );
                    }),
                    
                Tables\Filters\Filter::make('in_stock')
                    ->label('Cards On Hand')
                    ->query(fn (Builder $query): Builder => $query->where('quantity', '>', 0))
                    ->toggle()
                    ->default(),
                    
                Tables\Filters\Filter::make('low_stock')
                    ->label('Low Stock (< 4)')
                    ->query(fn (Builder $query): Builder => $query->where('quantity', '<', 4)->where('quantity', '>', 0))
                    ->toggle(),
                    
                Tables\Filters\Filter::make('out_of_stock')
                    ->label('Out of Stock')
                    ->query(fn (Builder $query): Builder => $query->where('quantity', 0))
                    ->toggle(),
            ])
            ->filtersLayout(Tables\Enums\FiltersLayout::AboveContentCollapsible)
            ->actions([
                Tables\Actions\Action::make('adjust_quantity')
                    ->label('Adjust')
                    ->icon('heroicon-o-calculator')
                    ->color('primary')
                    ->form([
                        Forms\Components\TextInput::make('adjustment')
                            ->label('Quantity Adjustment')
                            ->helperText('Enter positive number to add, negative to subtract')
                            ->numeric()
                            ->required()
                            ->extraInputAttributes(['style' => 'font-size: 1.25rem;']),
                    ])
                    ->action(function (Inventory $record, array $data): void {
                        $newQuantity = max(0, $record->quantity + $data['adjustment']);
                        $record->update(['quantity' => $newQuantity]);
                    })
                    ->successNotificationTitle('Quantity updated!'),
                    
                Tables\Actions\EditAction::make()
                    ->label('Edit'),
                    
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('No inventory yet')
            ->emptyStateDescription('Add your first card to start tracking inventory.')
            ->emptyStateIcon('heroicon-o-cube');
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
            'index' => Pages\ListInventories::route('/'),
            'create' => Pages\CreateInventory::route('/create'),
            'edit' => Pages\EditInventory::route('/{record}/edit'),
        ];
    }
}
