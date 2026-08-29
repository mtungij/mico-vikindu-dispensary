<form wire:submit="dispense" class="grid gap-6 xl:grid-cols-[1fr_22rem]">
    <x-card>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead><tr class="text-left text-xs uppercase text-slate-500"><th class="py-3">Medicine</th><th>Prescribed</th><th>Paid / Cleared</th><th>Dispensed</th><th>Can Dispense</th><th>Awaiting Payment</th><th>Stock</th><th>Quantity</th><th>Unavailable / Declined</th></tr></thead>
                <tbody>
                @foreach($form->lines as $i => $line)
                    @php
                        $itemId = $line['prescription_item_id'];
                        $item = $prescription->items->firstWhere('id', $itemId);
                        $financial = $financialRows[$itemId];
                        $selectedMedicine = \App\Models\Medicine::query()->forCurrentFacility()->find($line['medicine_id']);
                        $stock = (float) ($selectedMedicine?->currentStock($form->stock_location_id) ?? 0);
                        $maximum = min((float) $financial['remaining_paid_quantity'], (float) $financial['remaining_prescribed_quantity'], $stock);
                    @endphp
                    <tr class="border-t border-slate-100 align-top dark:border-slate-800">
                        <td class="py-3">
                            <select wire:model.live="form.lines.{{ $i }}.medicine_id" class="min-w-52 rounded-md border-slate-300 dark:border-slate-700 dark:bg-slate-900">
                                @foreach(\App\Models\Medicine::query()->forCurrentFacility()->where('is_active', true)->get() as $medicine)
                                    <option value="{{ $medicine->id }}">{{ $medicine->name }}</option>
                                @endforeach
                            </select>
                            <div class="mt-1 text-xs text-slate-500">Billed unit: {{ number_format((float) $financial['billed_unit_price'], 2) }}</div>
                        </td>
                        <td>{{ rtrim(rtrim($financial['prescribed_quantity'], '0'), '.') }}</td>
                        <td>{{ rtrim(rtrim($financial['paid_quantity'], '0'), '.') }}</td>
                        <td>{{ rtrim(rtrim($financial['dispensed_quantity'], '0'), '.') }}</td>
                        <td class="font-semibold text-emerald-700">{{ rtrim(rtrim(number_format($maximum, 3, '.', ''), '0'), '.') }}</td>
                        <td>{{ rtrim(rtrim($financial['unpaid_quantity'], '0'), '.') }}</td>
                        <td>{{ rtrim(rtrim(number_format($stock, 3, '.', ''), '0'), '.') }}</td>
                        <td><input type="number" min="0" max="{{ $maximum }}" step="{{ $item?->medicine?->dispensingUnit?->decimal_allowed ? '0.001' : '1' }}" wire:model="form.lines.{{ $i }}.quantity" class="w-24 rounded-md border-slate-300 dark:border-slate-700 dark:bg-slate-900"></td>
                        <td><x-text-input wire:model="terminalReasons.{{ $itemId }}" placeholder="Reason" /><button type="button" wire:click="declineItem({{ $itemId }})" class="mt-1 text-xs text-red-600">Mark unavailable</button></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </x-card>
    <x-card>
        <label class="text-sm">Location</label>
        <select wire:model.live="form.stock_location_id" class="mt-1 w-full rounded-md border-slate-300 dark:border-slate-700 dark:bg-slate-900">@foreach($locations as $location)<option value="{{ $location->id }}">{{ $location->name }}</option>@endforeach</select>
        <p class="mt-4 text-xs text-slate-500">Paid quantity and stock are recalculated inside the dispensing transaction. Unpaid quantities cannot be overridden.</p>
        <x-primary-button class="mt-4 w-full justify-center">Confirm Dispensing</x-primary-button>
    </x-card>
</form>
