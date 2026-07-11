<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Dispensing Labels - {{ $dispensing->dispensing_number }}</title>
    <style>
        body { font-family: sans-serif; color: #111827; }
        .label { width: 320px; border: 1px solid #111827; padding: 12px; margin: 10px; display: inline-block; vertical-align: top; }
        .small { font-size: 12px; color: #475569; }
        .name { font-weight: 700; margin-bottom: 8px; }
        @media print { .no-print { display: none; } }
    </style>
</head>
<body>
    <button class="no-print" onclick="window.print()">Print</button>
    @foreach ($dispensing->items as $item)
        <div class="label">
            <div class="small">{{ currentFacility()?->name }}</div>
            <div class="name">{{ $dispensing->patient?->full_name }}</div>
            <div><strong>{{ $item->medicine?->name }}</strong></div>
            <div>{{ $item->prescriptionItem?->dosage }} {{ $item->prescriptionItem?->frequency }} {{ $item->prescriptionItem?->duration }}</div>
            <div>Qty: {{ $item->quantity_dispensed }}</div>
            <div class="small">Dispensed: {{ $dispensing->dispensed_at?->format('d M Y H:i') }}</div>
            <div class="small">{{ $dispensing->dispensing_number }}</div>
        </div>
    @endforeach
</body>
</html>
