<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Dispensing Labels - {{ $dispensing->dispensing_number }}</title>
    <style>
        body { margin: 0; font-family: sans-serif; color: #111827; }
        .label { width: 320px; border: 1px solid #111827; padding: 10px; margin: 8px; display: inline-block; vertical-align: top; break-inside: avoid; page-break-inside: avoid; }
        .small { font-size: 12px; color: #475569; }
        .name { font-weight: 700; margin-bottom: 8px; }
        .medicine { margin-bottom: 6px; font-size: 16px; }
        .instructions { margin-top: 6px; }
        .footer { margin-top: 8px; }
        @media print {
            @page { margin: 6mm; }
            .no-print { display: none; }
            .label { margin: 3px; }
        }
    </style>
</head>
<body>
    <button class="no-print" onclick="window.print()">Print</button>
    @foreach ($dispensing->items as $item)
        @php
            $prescriptionItem = $item->prescriptionItem;
            $durationUnit = filled($prescriptionItem?->duration_unit)
                ? ((float) $prescriptionItem->duration_value === 1.0 ? str($prescriptionItem->duration_unit)->singular() : $prescriptionItem->duration_unit)
                : null;
            $quantity = (float) $item->dispensed_quantity;
            $dispensedQuantity = floor($quantity) === $quantity
                ? number_format($quantity, 0, '.', '')
                : rtrim(rtrim(number_format($quantity, 3, '.', ''), '0'), '.');
        @endphp
        <div class="label">
            <div class="small">{{ currentFacility()?->name }}</div>
            <div class="name">{{ $dispensing->patient?->full_name }}</div>
            <div class="medicine"><strong>{{ $item->medicine?->name }}</strong></div>
            @if (filled($prescriptionItem?->dose))
                <div><strong>Dose:</strong> {{ $prescriptionItem->dose }}</div>
            @endif
            @if (filled($prescriptionItem?->frequency))
                <div><strong>Frequency:</strong> {{ $prescriptionItem->frequency }}</div>
            @endif
            @if (filled($prescriptionItem?->duration_value) && filled($durationUnit))
                <div><strong>Duration:</strong> {{ $prescriptionItem->duration_value }} {{ $durationUnit }}</div>
            @endif
            @if (filled($prescriptionItem?->route))
                <div><strong>Route:</strong> {{ $prescriptionItem->route }}</div>
            @endif
            <div><strong>Qty:</strong> {{ $dispensedQuantity }}</div>
            @if (filled($item->instructions_snapshot))
                <div class="instructions"><strong>Instructions:</strong> {{ $item->instructions_snapshot }}</div>
            @endif
            <div class="small footer">Dispensed: {{ $dispensing->dispensed_at?->format('d M Y H:i') }}</div>
            <div class="small">{{ $dispensing->dispensing_number }}</div>
        </div>
    @endforeach
</body>
</html>
