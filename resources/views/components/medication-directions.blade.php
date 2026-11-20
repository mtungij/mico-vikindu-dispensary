@props(['item'])

@php
    $specified = fn ($value) => filled($value) ? $value : 'Not specified';
    $duration = match ($item->duration_unit) {
        'until_finished' => 'Until finished',
        'single_dose' => 'Single dose',
        default => filled($item->duration_value) && filled($item->duration_unit)
            ? $item->duration_value.' '.$item->duration_unit
            : 'Not specified',
    };
@endphp
<div {{ $attributes->class(['mt-2 w-full min-w-0 max-w-xl space-y-3 [overflow-wrap:anywhere] rounded-md bg-slate-50 p-3 text-sm text-slate-800 dark:bg-slate-900 dark:text-slate-200']) }}>
    <p class="font-semibold">Doctor medication order: {{ $specified($item->medication_name) }}</p>
    <dl class="grid min-w-0 grid-cols-1 gap-x-4 gap-y-3 sm:grid-cols-2">
        @foreach([
            'Strength' => $specified($item->strength),
            'Dose' => $specified($item->dose),
            'Route' => $specified(\App\Support\MedicationDirections::displayRoute($item->route)),
            'Frequency' => $specified(\App\Support\MedicationDirections::displayFrequency($item->frequency)),
            'Duration' => $duration,
            'Prescribed quantity' => $specified($item->quantity),
            'Dispensed quantity' => $specified($item->dispensed_quantity),
        ] as $label => $value)
            <div class="min-w-0"><dt class="font-medium">{{ $label }}:</dt> <dd class="mt-1">{{ $value }}</dd></div>
        @endforeach
        <div class="min-w-0 sm:col-span-2"><dt class="font-medium">Special / PRN instructions:</dt><dd class="mt-1 whitespace-pre-wrap">{{ $specified($item->instructions) }}</dd></div>
        <div class="min-w-0 sm:col-span-2"><dt class="font-medium">Indication / reason for PRN:</dt> <dd class="mt-1 whitespace-pre-wrap">{{ $specified($item->indication) }}</dd></div>
    </dl>
</div>
