<div>
    <x-secondary-button wire:click="edit"><x-lucide-pencil class="h-4 w-4" /> Badilisha</x-secondary-button>
    <x-modal :show="$showModal" title="Badilisha wasifu" max-width="lg" close="$set('showModal', false)">
        <form wire:submit="save" class="space-y-4">
            <div>
                <x-input-label value="Jina" />
                <x-text-input wire:model.live="name" class="mt-1" />
                <x-input-error :messages="$errors->get('name')" />
            </div>
            <div>
                <x-input-label value="Barua pepe" />
                <x-text-input wire:model.live="email" type="email" class="mt-1" />
                <x-input-error :messages="$errors->get('email')" />
            </div>
            <div>
                <x-input-label value="Simu" />
                <x-text-input wire:model.live="phone" class="mt-1" />
                <x-input-error :messages="$errors->get('phone')" />
            </div>
            <div class="flex justify-end gap-2">
                <x-secondary-button wire:click="$set('showModal', false)">Ghairi</x-secondary-button>
                <x-primary-button wire:target="save" wire:loading.attr="disabled"><x-loading-spinner wire:loading wire:target="save" /> Hifadhi</x-primary-button>
            </div>
        </form>
    </x-modal>
</div>
