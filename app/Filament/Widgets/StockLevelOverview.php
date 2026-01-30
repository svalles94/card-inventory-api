<?php

namespace App\Filament\Widgets;

use App\Models\Inventory;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StockLevelOverview extends BaseWidget
{
    protected function getStats(): array
    {
        $lowStock = Inventory::where('quantity', '>', 0)
            ->where('quantity', '<=', 4)
            ->count();
            
        $mediumStock = Inventory::where('quantity', '>', 4)
            ->where('quantity', '<=', 8)
            ->count();
            
        $highStock = Inventory::where('quantity', '>', 8)
            ->where('quantity', '<=', 12)
            ->count();
            
        $veryHighStock = Inventory::where('quantity', '>', 12)
            ->count();
        
        return [
            Stat::make('Low Stock (1-4)', number_format($lowStock))
                ->description('Cards needing restock')
                ->descriptionIcon('heroicon-o-exclamation-triangle')
                ->color('danger')
                ->url(route('filament.admin.resources.inventories.index', ['tableFilters' => ['low_stock' => ['isActive' => true]]])),
                
            Stat::make('Medium Stock (5-8)', number_format($mediumStock))
                ->description('Moderate inventory levels')
                ->descriptionIcon('heroicon-o-minus-circle')
                ->color('warning')
                ->url(route('filament.admin.resources.inventories.index', ['tableSearch' => '', 'tableFilters' => ['quantity_range' => ['min' => 5, 'max' => 8]]])),
                
            Stat::make('High Stock (9-12)', number_format($highStock))
                ->description('Well stocked items')
                ->descriptionIcon('heroicon-o-check-circle')
                ->color('success')
                ->url(route('filament.admin.resources.inventories.index', ['tableFilters' => ['quantity_range' => ['min' => 9, 'max' => 12]]])),
                
            Stat::make('Very High Stock (12+)', number_format($veryHighStock))
                ->description('Overstocked items')
                ->descriptionIcon('heroicon-o-arrow-trending-up')
                ->color('info')
                ->url(route('filament.admin.resources.inventories.index', ['tableFilters' => ['quantity_range' => ['min' => 13]]])),
        ];
    }
}
