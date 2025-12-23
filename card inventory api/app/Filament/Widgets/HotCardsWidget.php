<?php

namespace App\Filament\Widgets;

use App\Models\OrderItem;
use App\Models\Card;
use Filament\Widgets\Widget;
use Illuminate\Support\Carbon;

class HotCardsWidget extends Widget
{
    protected static string $view = 'filament.widgets.hot-cards-widget';
    
    protected int | string | array $columnSpan = 'full';
    
    public function getHotCards()
    {
        $sevenDaysAgo = Carbon::now()->subDays(7);
        
        // Get cards sold in last 7 days with consistent sales
        $hotCards = OrderItem::query()
            ->whereHas('order', function ($query) use ($sevenDaysAgo) {
                $query->where('ordered_at', '>=', $sevenDaysAgo)
                    ->where('status', '!=', 'cancelled');
            })
            ->selectRaw('card_id, SUM(quantity) as total_sold, COUNT(DISTINCT DATE(order_items.created_at)) as days_sold')
            ->whereNotNull('card_id')
            ->groupBy('card_id')
            ->having('days_sold', '>=', 5) // Sold on at least 5 of the last 7 days
            ->orderByDesc('total_sold')
            ->limit(10)
            ->with(['card'])
            ->get();
        
        return $hotCards;
    }
}
