<form wire:submit="receive" class="space-y-6">
    <x-card class="grid gap-4 md:grid-cols-3">
        <select wire:model="form.supplier_id" class="rounded-md border-slate-300 dark:border-slate-700 dark:bg-slate-900"><option value="">Supplier</option>@foreach($suppliers as $supplier)<option value="{{ $supplier->id }}">{{ $supplier->name }}</option>@endforeach</select>
        <select wire:model="form.stock_location_id" class="rounded-md border-slate-300 dark:border-slate-700 dark:bg-slate-900"><option value="">Location</option>@foreach($locations as $location)<option value="{{ $location->id }}">{{ $location->name }}</option>@endforeach</select>
        <x-text-input wire:model="form.supplier_invoice_number" placeholder="Supplier invoice" />
    </x-card>
    <x-card class="space-y-4">
        @foreach ($form->items as $index => $line)
            <div class="grid gap-3 md:grid-cols-5">
                <select wire:model="form.items.{{ $index }}.medicine_id" class="rounded-md border-slate-300 dark:border-slate-700 dark:bg-slate-900"><option value="">Medicine</option>@foreach($medicines as $medicine)<option value="{{ $medicine->id }}">{{ $medicine->name }}</option>@endforeach</select>
                <x-text-input wire:model="form.items.{{ $index }}.batch_number" placeholder="Batch" />
                <x-text-input type="date" wire:model="form.items.{{ $index }}.expiry_date" />
                <x-text-input wire:model="form.items.{{ $index }}.quantity_received" placeholder="Qty" />
                <x-text-input wire:model="form.items.{{ $index }}.unit_cost" placeholder="Unit cost" />
            </div>
        @endforeach
        <div class="flex justify-between"><x-secondary-button type="button" wire:click="addLine"><x-lucide-plus class="h-4 w-4" /> Line</x-secondary-button><x-primary-button>Receive</x-primary-button></div>
    </x-card>
</form>
