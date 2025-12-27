<?php

namespace App\Filament\Store\Resources\StoreCardResource\Pages;

use App\Filament\Store\Resources\StoreCardResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Session;

class ListStoreCards extends ListRecords
{
    protected static string $resource = StoreCardResource::class;

    public string $viewMode = 'list';

    protected $queryString = [
        'viewMode' => ['except' => 'list'],
    ];

    public function mount(): void
    {
        parent::mount();
        $this->viewMode = Session::get('cards_view_mode', 'list');
    }

    public function updatedViewMode(string $value): void
    {
        Session::put('cards_view_mode', $value);
    }

    protected function getTableContentGrid(): ?array
    {
        return $this->viewMode === 'grid'
            ? [
                'md' => 2,
                'lg' => 3,
                'xl' => 4,
            ]
            : null;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('toggle_view')
                ->label(fn () => $this->viewMode === 'grid' ? 'Switch to List' : 'Switch to Grid')
                ->icon(fn () => $this->viewMode === 'grid' ? 'heroicon-o-list-bullet' : 'heroicon-o-rectangle-group')
                ->color('gray')
                ->action(function () {
                    $this->viewMode = $this->viewMode === 'grid' ? 'list' : 'grid';
                    Session::put('cards_view_mode', $this->viewMode);
                }),
            Actions\Action::make('review_queue')
                ->label('Queued Updates')
                ->icon('heroicon-o-clipboard-document-list')
                ->color('primary')
                ->url(\App\Filament\Store\Pages\InventoryUpdateQueuePage::getUrl()),
        ];
    }
}

