<!DOCTYPE html>
<html lang="sw">
<head>
    <meta charset="utf-8">
    <title>Laboratory Report - {{ $order->order_number }}</title>
    <style>
        body { font-family: Arial, sans-serif; color: #0f172a; font-size: 13px; }
        .wrap { max-width: 940px; margin: 0 auto; }
        .header { display: flex; justify-content: space-between; border-bottom: 2px solid #0f766e; padding-bottom: 12px; margin-bottom: 18px; }
        h1, h2, p { margin: 0; }
        table { width: 100%; border-collapse: collapse; margin-top: 14px; }
        th, td { border: 1px solid #cbd5e1; padding: 8px; text-align: left; vertical-align: top; }
        th { background: #f1f5f9; }
        .muted { color: #64748b; }
        .signature { height: 52px; object-fit: contain; max-width: 180px; }
        @media print { button { display: none; } }
    </style>
</head>
<body>
<div class="wrap">
    <button onclick="window.print()">Print</button>
    <div class="header">
        <div>
            <h1>{{ $facility?->name ?? config('app.name') }}</h1>
            <p class="muted">{{ $facility?->phone }} {{ $facility?->email }}</p>
        </div>
        <div>
            <h2>Laboratory Report</h2>
            <p class="muted">{{ $order->order_number }}</p>
        </div>
    </div>

    <p><strong>Patient:</strong> {{ $order->patient?->fullName() }} | <strong>Visit:</strong> {{ $order->visit?->visit_number }} | <strong>Ordered:</strong> {{ $order->ordered_at?->format('d/m/Y H:i') }}</p>

    @forelse($results as $result)
        <h3>{{ $result->test?->name }}</h3>
        <table>
            <thead><tr><th>Parameter</th><th>Result</th><th>Unit</th><th>Reference Range</th><th>Flag</th></tr></thead>
            <tbody>
                @foreach($result->values as $value)
                    <tr>
                        <td>{{ $value->parameter_name_snapshot }}</td>
                        <td>{{ $value->displayValue() }}</td>
                        <td>{{ $value->unit_snapshot }}</td>
                        <td>{{ $value->reference_range_snapshot }}</td>
                        <td>{{ $value->abnormal_flag?->value }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        @php($signature = $result->verifier?->staffProfile?->activeSignature)
        <p style="margin-top: 12px;">
            @if($signature)
                <img class="signature" src="{{ route('staff.signatures.view', [$result->verifier->staffProfile, $signature]) }}" alt="Verifier signature"><br>
            @endif
            <strong>Verified by:</strong> {{ $result->verifier?->fullStaffName() ?? $result->verifier?->name }}
        </p>
    @empty
        <p>Hakuna matokeo yaliyorelease.</p>
    @endforelse
</div>
</body>
</html>
