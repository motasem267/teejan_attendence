<x-filament-panels::page>
    <div style="background: #ffffff; border: 1px solid #e5e7eb; border-radius: 12px; padding: 20px; box-shadow: 0 1px 2px rgba(0,0,0,.04); margin-bottom: 20px;">
        <form wire:submit.prevent="search">
            <div style="display: flex; align-items: flex-end; gap: 16px; flex-wrap: wrap;">
                <div style="flex: 1; min-width: 300px;">
                    {{ $this->form }}
                </div>
                <div style="display: flex; gap: 8px;">
                    <x-filament::button type="submit" icon="heroicon-o-magnifying-glass">
                        بحث
                    </x-filament::button>
                    <x-filament::button type="button" color="gray" wire:click="resetSearch" icon="heroicon-o-x-mark">
                        إعادة ضبط
                    </x-filament::button>
                </div>
            </div>
        </form>
    </div>

    <div class="space-y-6">
        {{ $this->table }}
    </div>
</x-filament-panels::page>
