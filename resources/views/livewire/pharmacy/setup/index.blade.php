<div class="space-y-6">
    <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
        <div class="flex flex-wrap gap-2">
            @foreach (['categories' => 'Categories', 'generics' => 'Generics', 'dosage-forms' => 'Dosage Forms', 'units' => 'Units', 'routes' => 'Routes', 'suppliers' => 'Suppliers', 'stock-locations' => 'Stock Locations'] as $key => $label)
                <a href="{{ route('settings.pharmacy.'.$key) }}" class="rounded-md px-3 py-2 text-sm font-medium {{ $section === $key ? 'bg-primary text-white' : 'bg-white text-slate-700 hover:bg-slate-100 dark:bg-card-dark dark:text-slate-200 dark:hover:bg-slate-800' }}">{{ $label }}</a>
            @endforeach
        </div>
        <div class="flex gap-2">
            <x-text-input wire:model.live.debounce.300ms="search" placeholder="Tafuta..." />
            <x-primary-button wire:click="create"><x-lucide-plus class="h-4 w-4" /> Add</x-primary-button>
        </div>
    </div>

    <x-card>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="text-left text-xs uppercase text-slate-500">
                        <th class="py-3">Name</th>
                        <th>Code</th>
                        <th>Status</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr class="border-t border-slate-100 dark:border-slate-800">
                            <td class="py-3 font-semibold">{{ $row->name }}</td>
                            <td>{{ $row->code ?? $row->symbol ?? '-' }}</td>
                            <td><x-badge :tone="$row->is_active ? 'green' : 'slate'">{{ $row->is_active ? 'Active' : 'Inactive' }}</x-badge></td>
                            <td class="text-right">
                                <button type="button" wire:click="edit({{ $row->id }})" class="rounded-md p-2 hover:bg-slate-100 dark:hover:bg-slate-800" title="Edit"><x-lucide-pencil class="h-4 w-4" /></button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="py-8 text-center text-slate-500">Hakuna records.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $rows->links() }}</div>
    </x-card>

    <x-modal :show="$showModal" title="Pharmacy Setup" close="$set('showModal', false)" maxWidth="2xl">
        @php($prefix = match($section) {
            'generics' => 'genericForm',
            'dosage-forms' => 'dosageForm',
            'units' => 'unitForm',
            'routes' => 'routeForm',
            'suppliers' => 'supplierForm',
            'stock-locations' => 'locationForm',
            default => 'categoryForm',
        })
        <form wire:submit="save" class="space-y-3">
            <x-text-input wire:model="{{ $prefix }}.name" placeholder="Name" />
            @if ($section === 'units')
                <x-text-input wire:model="{{ $prefix }}.symbol" placeholder="Symbol" />
            @else
                <x-text-input wire:model="{{ $prefix }}.code" placeholder="Code" />
            @endif
            @if ($section === 'suppliers')
                <x-text-input wire:model="{{ $prefix }}.phone_primary" placeholder="Phone" />
                <x-text-input wire:model="{{ $prefix }}.email" placeholder="Email" />
            @endif
            @if ($section !== 'suppliers')
                <x-textarea wire:model="{{ $prefix }}.description" rows="3" placeholder="Description" />
            @endif
            <div class="flex justify-end">
                <x-primary-button>Save</x-primary-button>
            </div>
        </form>
    </x-modal>
</div>
