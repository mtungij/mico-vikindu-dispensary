<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Medicine Labels - {{ $dispensing->dispensing_number }}</title>
    @vite(['resources/css/app.css'])
    <style>
        body { background: #f1f5f9; color: #0f172a; font-family: sans-serif; }
        .print-controls { display: flex; justify-content: center; gap: 8px; padding: 16px; }
        .print-controls button, .print-controls a { border-radius: 6px; padding: 9px 16px; font-size: 14px; font-weight: 700; }
        .print-controls button { border: 0; background: #0f766e; color: white; }
        .print-controls a { border: 1px solid #cbd5e1; background: white; color: #334155; text-decoration: none; }
        .labels { display: grid; justify-content: center; gap: 12px; padding: 0 16px 24px; }
        .medicine-label { width: 100mm; max-width: calc(100vw - 32px); break-inside: avoid; page-break-inside: avoid; }
        @media print {
            @page { margin: 5mm; }
            body { background: white; }
            .no-print { display: none !important; }
            .labels { display: block; padding: 0; }
            .medicine-label { width: 100%; max-width: none; margin: 0 0 4mm; box-shadow: none; break-after: page; page-break-after: always; }
            .medicine-label:last-child { break-after: auto; page-break-after: auto; }
        }
    </style>
</head>
<body>
    <div class="print-controls no-print">
        <a href="{{ route('pharmacy.dispensings.labels', $dispensing) }}">Back to Labels</a>
        <button type="button" onclick="window.print()">Print {{ $items->count() === 1 ? 'Medicine Label' : 'All Labels' }}</button>
    </div>
    <main class="labels">
        @foreach ($items as $item)
            @include('pharmacy.partials.dispensing-label', ['dispensing' => $dispensing, 'item' => $item, 'preview' => false])
        @endforeach
    </main>
</body>
</html>
