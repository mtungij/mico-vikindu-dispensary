<x-card>
    <div class="mb-4 flex items-center justify-between">
        <h3 class="font-semibold">Demo Modal CRUD</h3>
        <x-primary-button type="button" wire:click="create"><x-lucide-plus class="h-4 w-4" /> Ongeza</x-primary-button>
    </div>
    @forelse ($records as $record)
        <div wire:key="demo-record-{{ $record['id'] }}" class="flex items-center justify-between border-t border-slate-200 py-3 dark:border-slate-700">
            <div><p class="font-medium">{{ $record['name'] }}</p><p class="text-sm text-slate-500">{{ $record['email'] }}</p></div>
            <div class="flex gap-1">
                <x-icon-button wire:click="edit({{ $record['id'] }})"><x-lucide-pencil class="h-4 w-4" /></x-icon-button>
                <x-icon-button wire:click="confirmDelete({{ $record['id'] }})"><x-lucide-trash-2 class="h-4 w-4" /></x-icon-button>
            </div>
        </div>
    @empty
        <x-empty-state />
    @endforelse
    <x-modal :show="$showModal" :title="$editing ? 'Badilisha rekodi' : 'Ongeza rekodi'" max-width="lg" close="closeModal">
        <form wire:submit="save" class="space-y-4">
            <div><x-input-label value="Jina" /><x-text-input wire:model.live="name" class="mt-1" /><x-input-error :messages="$errors->get('name')" /></div>
            <div><x-input-label value="Barua pepe" /><x-text-input wire:model.live="email" type="email" class="mt-1" /><x-input-error :messages="$errors->get('email')" /></div>
            <div class="flex justify-end gap-2"><x-secondary-button wire:click="closeModal">Ghairi</x-secondary-button><x-primary-button wire:dirty.class="ring-2 ring-warning" wire:target="save" wire:loading.attr="disabled"><x-loading-spinner wire:loading wire:target="save" /> Hifadhi</x-primary-button></div>
        </form>
    </x-modal>
    <x-confirm-modal :show="$showConfirmModal" title="Futa rekodi" message="Rekodi hii itafutwa kwenye orodha ya demo." confirm="delete" cancel="$set('showConfirmModal', false)" confirm-text="Futa" />
</x-card>
