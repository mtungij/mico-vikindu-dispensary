<!DOCTYPE html>
<html lang="sw">
<head>
    <meta charset="utf-8">
    <title>Laboratory Result - {{ $result->order?->order_number }}</title>
    <style>
        body { font-family: Arial, sans-serif; color: #0f172a; font-size: 13px; }
        .wrap { max-width: 900px; margin: 0 auto; }
        .header { display: flex; justify-content: space-between; border-bottom: 2px solid #0f766e; padding-bottom: 12px; margin-bottom: 18px; }
        h1, h2, p { margin: 0; }
        table { width: 100%; border-collapse: collapse; margin-top: 14px; }
        th, td { border: 1px solid #cbd5e1; padding: 8px; text-align: left; }
        th { background: #f1f5f9; }
        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 8px 24px; }
        .muted { color: #64748b; }
        .signature { height: 62px; object-fit: contain; max-width: 220px; }
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
            <h2>Laboratory Result</h2>
            <p class="muted">{{ $result->order?->order_number }}</p>
        </div>
    </div>

    <div class="grid">
        <p><strong>Patient:</strong> {{ $result->order?->patient?->fullName() }}</p>
        <p><strong>Visit:</strong> {{ $result->order?->visit?->visit_number }}</p>
        <p><strong>Test:</strong> {{ $result->test?->name }}</p>
        <p><strong>Specimen:</strong> {{ $result->sample?->specimenType?->name }}</p>
        <p><strong>Sample No:</strong> {{ $result->sample?->sample_number }}</p>
        <p><strong>Released:</strong> {{ $result->released_at?->format('d/m/Y H:i') ?? $result->verified_at?->format('d/m/Y H:i') }}</p>
    </div>

    <table>
        <thead><tr><th>Parameter</th><th>Result</th><th>Unit</th><th>Reference Range</th><th>Flag</th></tr></thead>
        <tbody>
            @forelse($result->values as $value)
                <tr>
                    <td>{{ $value->parameter_name_snapshot }}</td>
                    <td>{{ $value->displayValue() }}</td>
                    <td>{{ $value->unit_snapshot }}</td>
                    <td>{{ $value->reference_range_snapshot }}</td>
                    <td>{{ $value->abnormal_flag?->value }}</td>
                </tr>
            @empty
                <tr><td colspan="5">{{ $result->overall_result }}</td></tr>
            @endforelse
        </tbody>
    </table>

    @if($result->interpretation || $result->comments)
        <p style="margin-top: 16px;"><strong>Interpretation:</strong> {{ $result->interpretation }}</p>
        <p style="margin-top: 8px;"><strong>Comments:</strong> {{ $result->comments }}</p>
    @endif

    <div style="margin-top: 34px;">
        @php($signature = $result->verifier?->staffProfile?->activeSignature)
        @if($signature)
            <img class="signature" src="{{ route('staff.signatures.view', [$result->verifier->staffProfile, $signature]) }}" alt="Verifier signature">
        @endif
        <p><strong>Verified by:</strong> {{ $result->verifier?->fullStaffName() ?? $result->verifier?->name }}</p>
        <p class="muted">{{ $result->verified_at?->format('d/m/Y H:i') }}</p>
    </div>
</div>
</body>
</html>
