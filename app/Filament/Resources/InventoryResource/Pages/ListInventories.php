<?php

namespace App\Filament\Resources\InventoryResource\Pages;

use App\Filament\Resources\InventoryResource;
use App\Models\Inventory;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Components\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListInventories extends ListRecords
{
    protected static string $resource = InventoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Add Card to Inventory')
                ->icon('heroicon-o-plus'),
        ];
    }
    
    public function getTabs(): array
    {
        return [
            'all' => Tab::make('All Cards')
                ->badge(Inventory::count()),
            'in_stock' => Tab::make('On Hand')
                ->badge(Inventory::where('quantity', '>', 0)->count())
                ->badgeColor('success')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('quantity', '>', 0)),
            'low_stock' => Tab::make('Low Stock')
                ->badge(Inventory::where('quantity', '>', 0)->where('quantity', '<', 4)->count())
                ->badgeColor('warning')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('quantity', '>', 0)->where('quantity', '<', 4)),
            'out_of_stock' => Tab::make('Out of Stock')
                ->badge(Inventory::where('quantity', 0)->count())
                ->badgeColor('danger')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('quantity', 0)),
        ];
    }
    
    protected function getFooterWidgets(): array
    {
        return [
            InventoryResource\Widgets\InventoryStatsWidget::class,
        ];
    }
}
