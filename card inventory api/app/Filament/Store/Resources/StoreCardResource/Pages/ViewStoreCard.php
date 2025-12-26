<?php

namespace App\Filament\Store\Resources\StoreCardResource\Pages;

use App\Filament\Store\Resources\StoreCardResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Auth;

class ViewStoreCard extends ViewRecord
{
    protected static string $resource = StoreCardResource::class;

    protected function getHeaderActions(): array
    {
        $currentStore = Auth::user()->currentStore();
        $user = Auth::user();
        
        return [
            Actions\Action::make('add_to_inventory')
                ->label('Add to Inventory')
                ->icon('heroicon-o-plus-circle')
                ->color('success')
                ->url(function () {
                    return \App\Filament\Store\Resources\StoreInventoryResource::getUrl('create', [
                        'card_id' => $this->record->id,
                    ]);
                })
                ->visible(function () use ($currentStore, $user) {
                    if (!$currentStore) return false;
                    return $user->hasRole($currentStore, 'owner') || $user->hasRole($currentStore, 'admin');
                }),
        ];
    }
}

