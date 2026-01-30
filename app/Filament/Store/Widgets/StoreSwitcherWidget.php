<?php

namespace App\Filament\Store\Widgets;

use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

class StoreSwitcherWidget extends Widget
{
    protected static string $view = 'filament.store.widgets.store-switcher';

    protected int | string | array $columnSpan = 'full';

    public function getCurrentStore()
    {
        return Auth::user()->currentStore();
    }

    public function getStores()
    {
        return Auth::user()->stores()->get();
    }

    public function switchStore($storeId): void
    {
        $user = Auth::user();
        $store = \App\Models\Store::find($storeId);

        if (!$store || !$user->canAccessStore($store)) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'You do not have access to this store.',
            ]);
            return;
        }

        Session::put('current_store_id', $store->id);
        
        // Redirect to refresh the page with new store context
        $this->redirect(request()->url());
    }
}

