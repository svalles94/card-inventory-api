<?php

namespace App\Filament\Resources\InventoryResource\Widgets;

use App\Models\Inventory;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class InventoryStatsWidget extends BaseWidget
{
    protected function getStats(): array
    {
        $totalCards = Inventory::sum('quantity');
        $uniqueCards = Inventory::where('quantity', '>', 0)->count();
        $totalValue = Inventory::whereNotNull('sell_price')
            ->selectRaw('SUM(quantity * sell_price) as total')
            ->value('total') ?? 0;
        $lowStockCount = Inventory::where('quantity', '>', 0)->where('quantity', '<', 4)->count();
        
        return [
            Stat::make('Total Cards On Hand', number_format($totalCards))
                ->description('Total card count across all locations')
                ->descriptionIcon('heroicon-o-cube')
                ->color('success'),
                
            Stat::make('Unique Cards', number_format($uniqueCards))
                ->description('Different cards in stock')
                ->descriptionIcon('heroicon-o-squares-2x2')
                ->color('info'),
                
            Stat::make('Total Inventory Value', '$' . number_format($totalValue, 2))
                ->description('Based on sell prices')
                ->descriptionIcon('heroicon-o-currency-dollar')
                ->color('warning'),
                
            Stat::make('Low Stock Alerts', number_format($lowStockCount))
                ->description('Cards with less than 4 copies')
                ->descriptionIcon('heroicon-o-exclamation-triangle')
                ->color($lowStockCount > 0 ? 'danger' : 'success'),
        ];
    }
    
    protected function getColumns(): int
    {
        return 4;
    }
}
