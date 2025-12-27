@php
    /** @var \App\Filament\Store\Pages\InventoryUpdateQueuePage $this */
@endphp

<x-filament-panels::page>
    <x-filament::section>
        <x-slot name="heading">Queued Inventory Updates</x-slot>
        <x-slot name="description">Review and apply your queued quantity and sell price changes.</x-slot>

        <form wire:submit.prevent="submit" class="space-y-6">
            {{ $this->form }}
            <div class="flex items-center gap-3">
                <x-filament::button type="submit" icon="heroicon-o-check-circle" color="primary">
                    Apply Updates
                </x-filament::button>
                <x-filament::button wire:click.prevent="callMountedAction('clear_queue')" icon="heroicon-o-trash" color="danger" outlined>
                    Clear Queue
                </x-filament::button>
            </div>
        </form>
    </x-filament::section>
</x-filament-panels::page>
