<x-filament-panels::page>
    <div class="space-y-6">
        <!-- Instructions Card -->
        <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
                How to Use Bulk Update
            </h2>
            <ol class="list-decimal list-inside space-y-2 text-gray-700 dark:text-gray-300">
                <li>Select the game and location you want to update</li>
                <li>Click "Download CSV Template" to get a pre-filled spreadsheet</li>
                <li>Open the CSV file in Excel, Google Sheets, or any spreadsheet program</li>
                <li>Fill in the "new_quantity" column with your inventory counts</li>
                <li>Optionally update buy_price, sell_price, and market_price columns</li>
                <li>Save the file and upload it back here</li>
                <li>Click "Import CSV" to update your inventory</li>
            </ol>
        </div>

        <!-- Form -->
        <form wire:submit="uploadCsv">
            {{ $this->form }}

            <div class="mt-6 flex gap-4">
                <x-filament::button
                    wire:click="downloadTemplate"
                    type="button"
                    color="info"
                    icon="heroicon-o-arrow-down-tray"
                >
                    Download CSV Template
                </x-filament::button>

                <x-filament::button
                    type="submit"
                    icon="heroicon-o-arrow-up-tray"
                    wire:loading.attr="disabled"
                    wire:target="uploadCsv"
                >
                    <span wire:loading.remove wire:target="uploadCsv">Import CSV</span>
                    <span wire:loading wire:target="uploadCsv">Importing...</span>
                </x-filament::button>
            </div>
        </form>

        <!-- Progress Bar -->
        @if($isImporting || !empty($importProgressKey))
        <div 
            wire:poll.2s="getImportProgress"
            class="bg-white dark:bg-gray-800 shadow rounded-lg p-6 mt-6"
        >
            @php
                $progress = $this->progress ?? $this->getImportProgress();
            @endphp
            
            @if($progress)
                <div class="space-y-4">
                    <div class="flex items-center justify-between">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                            Import Progress
                        </h3>
                        <span class="text-sm text-gray-600 dark:text-gray-400">
                            {{ $progress['processed'] }} / {{ $progress['total'] }} rows
                        </span>
                    </div>
                    
                    <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-4 overflow-hidden">
                        <div 
                            class="h-full bg-primary-600 dark:bg-primary-500 transition-all duration-300 ease-out rounded-full"
                            style="width: {{ $progress['percentage'] ?? 0 }}%"
                        ></div>
                    </div>
                    
                    <div class="text-sm text-gray-600 dark:text-gray-400">
                        {{ $progress['message'] ?? 'Processing...' }}
                    </div>
                    
                    @if($progress['status'] === 'processing')
                        <div class="grid grid-cols-3 gap-4 text-sm">
                            <div>
                                <span class="text-gray-500 dark:text-gray-400">Created:</span>
                                <span class="font-semibold text-green-600 dark:text-green-400">{{ $progress['created'] ?? 0 }}</span>
                            </div>
                            <div>
                                <span class="text-gray-500 dark:text-gray-400">Updated:</span>
                                <span class="font-semibold text-blue-600 dark:text-blue-400">{{ $progress['updated'] ?? 0 }}</span>
                            </div>
                            <div>
                                <span class="text-gray-500 dark:text-gray-400">Failed:</span>
                                <span class="font-semibold text-red-600 dark:text-red-400">{{ $progress['failed'] ?? 0 }}</span>
                            </div>
                        </div>
                    @endif
                    
                    @if($progress['status'] === 'completed')
                        <div class="mt-4 p-4 bg-green-50 dark:bg-green-900/20 rounded-lg">
                            <p class="text-green-800 dark:text-green-200 font-semibold">
                                ✓ Import completed successfully!
                            </p>
                            <p class="text-sm text-green-700 dark:text-green-300 mt-2">
                                Created: {{ $progress['created'] }}, Updated: {{ $progress['updated'] }}, Failed: {{ $progress['failed'] }}
                            </p>
                        </div>
                    @endif
                    
                    @if($progress['status'] === 'error')
                        <div class="mt-4 p-4 bg-red-50 dark:bg-red-900/20 rounded-lg">
                            <p class="text-red-800 dark:text-red-200 font-semibold">
                                ✗ Import failed
                            </p>
                            <p class="text-sm text-red-700 dark:text-red-300 mt-2">
                                {{ $progress['message'] }}
                            </p>
                        </div>
                    @endif
                </div>
            @else
                <div class="text-center py-4">
                    <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-primary-600"></div>
                    <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">Starting import...</p>
                </div>
            @endif
        </div>
        @endif

        <!-- Tips Card -->
        <div class="bg-blue-50 dark:bg-blue-900/20 rounded-lg p-6">
            <h3 class="text-sm font-semibold text-blue-900 dark:text-blue-100 mb-2">
                💡 Tips
            </h3>
            <ul class="list-disc list-inside space-y-1 text-sm text-blue-800 dark:text-blue-200">
                <li>Leave "new_quantity" empty for cards you don't want to update</li>
                <li>The "current_quantity" column shows your existing inventory for reference</li>
                <li>Price fields are optional - leave blank to keep existing prices</li>
                <li>Make sure to save your CSV file before uploading</li>
                <li>Large imports may take a few moments to process</li>
            </ul>
        </div>
    </div>
</x-filament-panels::page>
