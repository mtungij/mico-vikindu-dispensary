<div class="space-y-6">
    <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
        <div class="flex flex-col gap-2 sm:flex-row">
            <x-text-input wire:model.live.debounce.300ms="search" placeholder="Tafuta dawa..." />
            <x-select-input wire:model.live="billingStatus"><option value="">All billing statuses</option><option value="ready">Billing Ready</option><option value="missing_service">Missing Service</option><option value="missing_price">Missing Price</option><option value="needs_review">Needs Review</option></x-select-input>
        </div>
        <x-primary-button wire:click="create"><x-lucide-plus class="h-4 w-4" /> Dawa</x-primary-button>
    </div>

    <x-card>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead><tr class="text-left text-xs uppercase text-slate-500"><th class="py-3">Medicine</th><th>Generic</th><th>Strength</th><th>Stock</th><th>Billing service</th><th>Current cash price</th><th>Billing status</th><th class="text-right">Actions</th></tr></thead>
                <tbody>
                    @foreach($medicines as $medicine)
                        <tr class="border-t border-slate-100 dark:border-slate-800">
                            <td class="py-3 font-semibold">{{ $medicine->name }}<div class="text-xs text-slate-500">{{ $medicine->code }} · {{ $medicine->is_active ? 'Active' : 'Inactive' }}</div></td>
                            <td>{{ $medicine->generic?->name }}</td>
                            <td>{{ $medicine->strength }}</td>
                            <td>{{ $medicine->batches_sum_available_quantity ?? 0 }} {{ $medicine->dispensingUnit?->symbol }}</td>
                            <td>{{ $medicine->service?->name ?? 'Missing' }}</td>
                            <td>{{ $medicine->billing_readiness['price'] ? $medicine->billing_readiness['label'] : '—' }}</td>
                            <td><x-badge :tone="$medicine->billing_readiness['ready'] ? 'success' : 'danger'">{{ $medicine->billing_readiness['ready'] ? 'Ready' : $medicine->billing_readiness['label'] }}</x-badge></td>
                            <td class="text-right whitespace-nowrap">
                                @if($medicine->service)
                                    @can('managePrices', $medicine->service)<a href="{{ route('settings.services.prices', $medicine->service) }}" class="rounded-md p-2 text-primary hover:bg-slate-100 dark:hover:bg-slate-800" title="Manage billing prices">Prices</a>@endcan
                                @endif
                                <a href="{{ route('pharmacy.medicines.stock-card',$medicine) }}" class="rounded-md p-2 hover:bg-slate-100 dark:hover:bg-slate-800"><x-lucide-clipboard-list class="inline h-4 w-4" /></a>
                                <button wire:click="edit({{ $medicine->id }})" class="rounded-md p-2 hover:bg-slate-100 dark:hover:bg-slate-800"><x-lucide-pencil class="inline h-4 w-4" /></button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $medicines->links() }}</div>
    </x-card>

    <x-modal :show="$showModal" title="Medicine" close="$set('showModal', false)" maxWidth="3xl">
        <form wire:submit="save" class="grid gap-3 md:grid-cols-2">
            <div><x-input-label value="Name" /><x-text-input wire:model="form.name" class="w-full" /><x-input-error :messages="$errors->get('form.name')" class="mt-1" /></div>
            <div><x-input-label value="Code" /><x-text-input wire:model="form.code" class="w-full" /><x-input-error :messages="$errors->get('form.code')" class="mt-1" /></div>
            <div><x-input-label value="Strength" /><x-text-input wire:model="form.strength" class="w-full" /></div>
            @if(auth()->user()->can('pharmacy.manage-prices') || auth()->user()->can('services.manage-prices'))
                <div class="rounded-md border border-slate-200 p-3 dark:border-slate-700">
                    <x-input-label value="Cash price (TSh)" />
                    <x-text-input type="number" min="0" step="0.01" wire:model="form.cash_price" class="w-full" placeholder="500.00" />
                    <x-input-error :messages="array_merge($errors->get('form.cash_price'), $errors->get('cash_price'))" class="mt-1" />
                    <p class="mt-1 text-xs text-slate-500">This is the authoritative cash billing price. Changes create a new effective price version.</p>
                </div>
            @endif
            <div class="rounded-md border border-slate-200 p-3 dark:border-slate-700">
                <label class="flex items-center gap-2 text-sm font-medium"><x-checkbox wire:model.live="form.use_custom_billing_service" /> Advanced: use custom Billing Service</label>
                @if($form->use_custom_billing_service)
                    <x-select-input wire:model="form.service_id" class="mt-2 w-full"><option value="">System managed (recommended)</option>@foreach($services as $service)<option value="{{ $service->id }}">{{ $service->name }} ({{ $service->is_active ? 'Active' : 'Inactive' }})</option>@endforeach</x-select-input>
                    <x-input-error :messages="array_merge($errors->get('form.service_id'), $errors->get('service_id'))" class="mt-1" />
                @else
                    <p class="mt-2 text-xs text-slate-500">A medicine Billing Service is created or reused automatically. Existing mappings are preserved.</p>
                @endif
            </div>
            <div><x-input-label value="Inventory reference price" /><x-text-input wire:model="form.default_dispensing_price" class="w-full" /><p class="mt-1 text-xs text-slate-500">Reference/fallback value only. It is never converted into a patient charge automatically.</p></div>
            <div><x-input-label value="Purchase unit" /><x-select-input wire:model="form.purchase_unit_id" class="w-full"><option value="">Purchase unit</option>@foreach($units as $unit)<option value="{{ $unit->id }}">{{ $unit->name }}</option>@endforeach</x-select-input></div>
            <div><x-input-label value="Dispensing unit" /><x-select-input wire:model="form.dispensing_unit_id" class="w-full"><option value="">Dispensing unit</option>@foreach($units as $unit)<option value="{{ $unit->id }}">{{ $unit->name }}</option>@endforeach</x-select-input></div>
            <div><x-input-label value="Generic" /><x-select-input wire:model="form.generic_medicine_id" class="w-full"><option value="">Generic</option>@foreach($generics as $generic)<option value="{{ $generic->id }}">{{ $generic->name }}</option>@endforeach</x-select-input></div>
            <div><x-input-label value="Dosage form" /><x-select-input wire:model="form.dosage_form_id" class="w-full"><option value="">Dosage form</option>@foreach($forms as $df)<option value="{{ $df->id }}">{{ $df->name }}</option>@endforeach</x-select-input></div>
            <label class="flex items-center gap-2"><x-checkbox wire:model="form.is_active" /> Active</label>
            <div class="md:col-span-2 flex justify-end"><x-primary-button>Save</x-primary-button></div>
        </form>
    </x-modal>
</div>
