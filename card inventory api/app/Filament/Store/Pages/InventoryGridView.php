<?php

namespace App\Filament\Store\Pages;

use App\Filament\Store\Resources\StoreInventoryResource;
use App\Models\Card;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class InventoryGridView extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-group';

    protected static ?string $navigationGroup = null;

    protected static ?string $navigationLabel = 'Grid Add';

    protected static ?int $navigationSort = 2;

    protected static bool $shouldRegisterNavigation = false;

    protected static string $view = 'filament.store.pages.inventory-grid-view';

    public function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->query($this->getQuery())
            ->columns([
                Tables\Columns\ImageColumn::make('image')
                    ->label('Card')
                    ->size(80)
                    ->defaultImageUrl('/images/card-placeholder.png'),
                Tables\Columns\TextColumn::make('name')
                    ->label('Name')
                    ->searchable()
                    ->sortable()
                    ->wrap(),
                Tables\Columns\TextColumn::make('set_code')
                    ->label('Set')
                    ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('card_number')
                    ->label('#')
                    ->sortable(),
            ])
            ->actions([
                Tables\Actions\Action::make('add')
                    ->label('Add to Inventory')
                    ->icon('heroicon-o-plus-circle')
                    ->color('primary')
                    ->url(fn (Card $record) => StoreInventoryResource::getUrl('create', ['card_id' => $record->id]))
                    ->openUrlInNewTab(),
            ])
            ->contentGrid([
                'md' => 2,
                'lg' => 3,
                'xl' => 4,
            ])
            ->paginationPageOptions([12, 24, 48])
            ->defaultSort('name');
    }

    protected function getQuery(): Builder
    {
        return Card::query();
    }

    public function getHeading(): string
    {
        return 'Grid Add';
    }

    public function getSubheading(): ?string
    {
        $store = Auth::user()?->currentStore();
        return $store ? "Browse cards and jump to add for {$store->name}" : 'Browse cards and add to inventory';
    }
}
