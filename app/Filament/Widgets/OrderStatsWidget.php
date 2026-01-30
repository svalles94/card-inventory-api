<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;

class OrderStatsWidget extends BaseWidget
{
    protected function getStats(): array
    {
        $today = Carbon::today();
        $last30Days = Carbon::now()->subDays(30);
        
        // Total orders (last 30 days)
        $totalOrders = Order::where('ordered_at', '>=', $last30Days)
            ->where('status', '!=', 'cancelled')
            ->count();
        
        // Total revenue
        $totalRevenue = Order::where('ordered_at', '>=', $last30Days)
            ->where('status', '!=', 'cancelled')
            ->sum('total_amount');
        
        // Average order value
        $averageOrder = $totalOrders > 0 ? $totalRevenue / $totalOrders : 0;
        
        // Orders today
        $ordersToday = Order::whereDate('ordered_at', $today)
            ->where('status', '!=', 'cancelled')
            ->count();
        
        // Yesterday's orders for comparison
        $ordersYesterday = Order::whereDate('ordered_at', $today->copy()->subDay())
            ->where('status', '!=', 'cancelled')
            ->count();
        
        $todayChange = $ordersYesterday > 0 
            ? round((($ordersToday - $ordersYesterday) / $ordersYesterday) * 100, 1)
            : 0;
        
        return [
            Stat::make('Total Orders (30d)', number_format($totalOrders))
                ->description('Orders in the last 30 days')
                ->descriptionIcon('heroicon-o-shopping-cart')
                ->color('info'),
                
            Stat::make('Total Revenue (30d)', '$' . number_format($totalRevenue, 2))
                ->description('Sales in the last 30 days')
                ->descriptionIcon('heroicon-o-currency-dollar')
                ->color('success'),
                
            Stat::make('Average Order', '$' . number_format($averageOrder, 2))
                ->description('Average order value')
                ->descriptionIcon('heroicon-o-calculator')
                ->color('warning'),
                
            Stat::make('Orders Today', number_format($ordersToday))
                ->description(($todayChange >= 0 ? '+' : '') . $todayChange . '% from yesterday')
                ->descriptionIcon($todayChange >= 0 ? 'heroicon-o-arrow-trending-up' : 'heroicon-o-arrow-trending-down')
                ->color($todayChange >= 0 ? 'success' : 'danger'),
        ];
    }
}
