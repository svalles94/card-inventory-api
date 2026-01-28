<?php

namespace App\Providers;

use App\Models\Inventory;
use App\Observers\InventoryObserver;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Register inventory observer for automatic marketplace sync
        Inventory::observe(InventoryObserver::class);
        
        // Ensure storage directories exist (fixes Docker permission issues)
        $this->ensureStorageDirectoriesExist();
    }
    
    /**
     * Ensure all required storage directories exist with proper permissions
     */
    protected function ensureStorageDirectoriesExist(): void
    {
        $directories = [
            storage_path('app/public'),
            storage_path('app/tmp'),
            storage_path('app/temp-csv'),
            storage_path('framework/cache'),
            storage_path('framework/cache/data'),
            storage_path('framework/sessions'),
            storage_path('framework/views'),
            storage_path('logs'),
        ];
        
        foreach ($directories as $directory) {
            if (!file_exists($directory)) {
                @mkdir($directory, 0755, true);
            }
        }
    }
}
