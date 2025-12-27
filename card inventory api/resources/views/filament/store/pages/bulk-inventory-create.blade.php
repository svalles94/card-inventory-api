@php
    /** @var \App\Filament\Store\Pages\BulkInventoryCreate $this */
@endphp

<x-filament-panels::page>
    <x-filament::section>
        <x-slot name="heading">Update Inventory</x-slot>
        <x-slot name="description">Review selected cards and add inventory details.</x-slot>

        <form wire:submit.prevent="submit" class="space-y-6">
            {{ $this->form }}
            <x-filament::button type="submit" color="primary" icon="heroicon-o-check-circle">
                Save Inventory
            </x-filament::button>
        </form>
    </x-filament::section>
</x-filament-panels::page>
