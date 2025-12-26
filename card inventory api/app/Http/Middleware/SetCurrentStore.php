<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class SetCurrentStore
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();
        
        if (!$user) {
            return $next($request);
        }

        // Platform admins don't need store context in store panel
        // (They should use admin panel instead, but if they access store panel, skip store scoping)
        if ($user->isPlatformAdmin()) {
            return $next($request);
        }

        // Get current store from session or route parameter
        $storeId = $request->route('store') 
            ?? session('current_store_id')
            ?? $user->stores()->first()?->id;

        if ($storeId) {
            $store = \App\Models\Store::find($storeId);
            
            // Verify user has access to this store
            if ($store && $user->canAccessStore($store)) {
                session(['current_store_id' => $store->id]);
                app()->instance('current_store', $store);
            } else {
                // User doesn't have access, redirect to store selection
                if ($request->route()->getName() !== 'filament.store.pages.store-select') {
                    return redirect()->route('filament.store.pages.store-select');
                }
            }
        } else {
            // No store selected
            if ($user->stores()->count() === 0) {
                // User has no stores - redirect to registration
                return redirect()->route('store.register');
            } else if ($user->stores()->count() > 1) {
                // User has multiple stores - redirect to store selection
                if ($request->route()->getName() !== 'filament.store.pages.store-select') {
                    return redirect()->route('filament.store.pages.store-select');
                }
            } else {
                // User has exactly one store - auto-select it
                $onlyStore = $user->stores()->first();
                session(['current_store_id' => $onlyStore->id]);
                app()->instance('current_store', $onlyStore);
            }
        }

        return $next($request);
    }
}

