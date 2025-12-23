<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

class SalesVolumeChart extends ChartWidget
{
    protected static ?string $heading = 'Sales Volume (Last 30 Days)';
    
    protected static ?string $maxHeight = '300px';
    
    public ?string $filter = '30days';

    protected function getData(): array
    {
        $days = match($this->filter) {
            '7days' => 7,
            '30days' => 30,
            '90days' => 90,
            default => 30,
        };
        
        $startDate = now()->subDays($days)->startOfDay();
        
        // Get daily sales data
        $salesData = Order::where('ordered_at', '>=', $startDate)
            ->where('status', '!=', 'cancelled')
            ->selectRaw('DATE(ordered_at) as date, SUM(total_amount) as total, COUNT(*) as count')
            ->groupBy('date')
            ->orderBy('date')
            ->get();
        
        // Fill in missing dates with zeros
        $dates = collect();
        $amounts = collect();
        $orderCounts = collect();
        
        for ($i = 0; $i < $days; $i++) {
            $date = now()->subDays($days - $i - 1)->format('Y-m-d');
            $dateLabel = now()->subDays($days - $i - 1)->format('M j');
            
            $dayData = $salesData->firstWhere('date', $date);
            
            $dates->push($dateLabel);
            $amounts->push($dayData ? (float) $dayData->total : 0);
            $orderCounts->push($dayData ? $dayData->count : 0);
        }
        
        return [
            'datasets' => [
                [
                    'label' => 'Sales Amount ($)',
                    'data' => $amounts->toArray(),
                    'borderColor' => 'rgb(59, 130, 246)',
                    'backgroundColor' => 'rgba(59, 130, 246, 0.1)',
                    'tension' => 0.3,
                ],
            ],
            'labels' => $dates->toArray(),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
    
    protected function getFilters(): ?array
    {
        return [
            '7days' => 'Last 7 Days',
            '30days' => 'Last 30 Days',
            '90days' => 'Last 90 Days',
        ];
    }
}
