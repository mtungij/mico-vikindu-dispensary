<div>
    <x-primary-button type="button" wire:click="open"><x-lucide-shield-check class="h-4 w-4" /> Badilisha</x-primary-button>
    <x-modal :show="$showModal" title="Badilisha nenosiri" max-width="lg" close="$set('showModal', false)">
        <form wire:submit="save" class="space-y-4">
            <div>
                <x-input-label value="Nenosiri la sasa" />
                <x-text-input wire:model.live="current_password" type="password" class="mt-1" />
                <x-input-error :messages="$errors->get('current_password')" />
            </div>
            <div>
                <x-input-label value="Nenosiri jipya" />
                <x-text-input wire:model.live="password" type="password" class="mt-1" />
                <x-input-error :messages="$errors->get('password')" />
            </div>
            <div>
                <x-input-label value="Rudia nenosiri jipya" />
                <x-text-input wire:model.live="password_confirmation" type="password" class="mt-1" />
            </div>
            <div class="flex justify-end gap-2">
                <x-secondary-button wire:click="$set('showModal', false)">Ghairi</x-secondary-button>
                <x-primary-button wire:target="save" wire:loading.attr="disabled"><x-loading-spinner wire:loading wire:target="save" /> Hifadhi</x-primary-button>
            </div>
        </form>
    </x-modal>
</div>
