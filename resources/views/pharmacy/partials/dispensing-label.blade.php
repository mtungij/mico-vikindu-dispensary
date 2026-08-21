@php
    $prescriptionItem = $item->prescriptionItem;
    $durationUnit = filled($prescriptionItem?->duration_unit)
        ? ((float) $prescriptionItem->duration_value === 1.0 ? str($prescriptionItem->duration_unit)->singular() : $prescriptionItem->duration_unit)
        : null;
    $quantity = (float) $item->dispensed_quantity;
    $quantityLabel = floor($quantity) === $quantity
        ? number_format($quantity, 0, '.', '')
        : rtrim(rtrim(number_format($quantity, 3, '.', ''), '0'), '.');
    $quantityUnit = \App\Support\MedicationDirections::quantityUnit($item->medicine, $quantity);
@endphp
<article class="medicine-label overflow-hidden rounded-xl border border-slate-300 bg-white text-slate-950 shadow-sm dark:border-slate-600 {{ ($preview ?? false) ? 'dark:bg-slate-100' : '' }}">
    <div class="border-b-4 border-primary bg-slate-50 px-5 py-4 text-center">
        <p class="text-sm font-extrabold uppercase tracking-wide">{{ currentFacility()?->name }}</p>
        <p class="mt-1 text-[11px] font-bold uppercase tracking-[0.22em] text-primary">Pharmacy</p>
    </div>
    <div class="space-y-4 p-5">
        <div class="grid grid-cols-2 gap-x-4 gap-y-1 text-xs">
            <p><span class="text-slate-500">Patient:</span> <strong>{{ $dispensing->patient?->fullName() }}</strong></p>
            <p><span class="text-slate-500">Patient No:</span> <strong>{{ $dispensing->patient?->patient_number }}</strong></p>
            <p><span class="text-slate-500">Date:</span> <strong>{{ $dispensing->dispensed_at?->format('d M Y') }}</strong></p>
            <p><span class="text-slate-500">Dispensing No:</span> <strong>{{ $dispensing->dispensing_number }}</strong></p>
        </div>
        <div class="border-y border-slate-200 py-3 text-center"><h3 class="text-base font-extrabold uppercase leading-tight">{{ $item->medicine?->name }}</h3></div>
        <dl class="grid grid-cols-[auto_1fr] gap-x-3 gap-y-1.5 text-sm">
            @if (filled($prescriptionItem?->dose))<dt class="font-semibold">Take:</dt><dd>{{ $prescriptionItem->dose }}</dd>@endif
            @if (filled($prescriptionItem?->frequency))<dt class="font-semibold">Frequency:</dt><dd>{{ \App\Support\MedicationDirections::displayFrequency($prescriptionItem->frequency) }}</dd>@endif
            @if (filled($prescriptionItem?->duration_value) && filled($durationUnit))<dt class="font-semibold">Duration:</dt><dd>{{ in_array($prescriptionItem->duration_unit, ['until_finished', 'single_dose'], true) ? str($prescriptionItem->duration_unit)->replace('_', ' ')->title() : $prescriptionItem->duration_value.' '.$durationUnit }}</dd>@endif
            @if (filled($prescriptionItem?->route))<dt class="font-semibold">Route:</dt><dd>{{ \App\Support\MedicationDirections::displayRoute($prescriptionItem->route) }}</dd>@endif
            <dt class="font-semibold">Quantity:</dt><dd class="text-base font-extrabold">{{ $quantityLabel }}{{ $quantityUnit ? ' '.$quantityUnit : '' }}</dd>
            @if (filled($item->instructions_snapshot))<dt class="font-semibold">Instructions:</dt><dd>{{ $item->instructions_snapshot }}</dd>@endif
        </dl>
        <div class="rounded-md bg-amber-50 px-3 py-2 text-center text-[11px] font-medium text-amber-950">
            <p>Use medicine as directed by your doctor.</p><p>Keep out of reach of children.</p>
        </div>
    </div>
</article>
