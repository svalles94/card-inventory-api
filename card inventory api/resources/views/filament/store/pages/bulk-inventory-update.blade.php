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
                >
                    Import CSV
                </x-filament::button>
            </div>
        </form>

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
