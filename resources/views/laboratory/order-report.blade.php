<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Laboratory Report - {{ $order->report_number ?? $order->order_number }}</title>
    <style>
        @page { margin: 24mm 12mm 22mm; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: Helvetica, Arial, sans-serif; color: #172033; font-size: 9.5px; line-height: 1.35; }
        .report { max-width: 920px; margin: 0 auto; }
        .actions { margin-bottom: 14px; padding: 10px; background: #f1f5f9; border-radius: 6px; }
        .action { display: inline-block; margin-right: 6px; padding: 7px 12px; border-radius: 5px; background: #0f766e; color: #fff; text-decoration: none; font-weight: bold; }
        .action.secondary { background: #334155; }
        .header-table, .details-table, .verification-table { width: 100%; border-collapse: collapse; }
        .header-table td { vertical-align: middle; border: 0; padding: 0 0 10px; }
        .logo { max-width: 78px; max-height: 64px; margin-right: 12px; }
        .facility-name { margin: 0 0 3px; font-size: 17px; color: #0f766e; }
        .facility-line { margin: 1px 0; color: #475569; }
        .report-title { text-align: right; }
        .report-title h1 { margin: 0; font-size: 15px; letter-spacing: .5px; }
        .report-title p { margin: 3px 0 0; color: #475569; }
        .rule { border: 0; border-top: 2px solid #0f766e; margin: 0 0 12px; }
        .section-title { margin: 13px 0 5px; padding: 5px 7px; color: #fff; background: #0f766e; font-size: 10px; text-transform: uppercase; letter-spacing: .35px; }
        .details-table td { width: 25%; padding: 4px 6px; border: 1px solid #d6dee8; vertical-align: top; }
        .label { display: block; color: #64748b; font-size: 8px; text-transform: uppercase; }
        .value { display: block; margin-top: 1px; font-weight: bold; overflow-wrap: anywhere; }
        .results { width: 100%; margin-top: 5px; border-collapse: collapse; page-break-inside: auto; }
        .results thead { display: table-header-group; }
        .results tr { page-break-inside: avoid; page-break-after: auto; }
        .results th, .results td { border: 1px solid #bcc8d6; padding: 5px 4px; text-align: left; vertical-align: top; }
        .results th { background: #e8f3f2; color: #153e3a; font-size: 8px; text-transform: uppercase; }
        .test-row td { background: #f8fafc; font-weight: bold; color: #0f766e; padding-top: 7px; }
        .center { text-align: center !important; }
        .nowrap { white-space: nowrap; }
        .narrative { white-space: pre-wrap; overflow-wrap: anywhere; }
        .muted { color: #64748b; }
        .verification-table td { width: 33.333%; padding: 7px; border: 1px solid #d6dee8; vertical-align: top; }
        .signature { display: block; max-width: 130px; max-height: 42px; margin: 4px 0; }
        .signature-line { width: 190px; margin-top: 28px; border-top: 1px solid #334155; }
        .footer { position: fixed; right: 0; bottom: -15mm; left: 0; padding-top: 5px; border-top: 1px solid #cbd5e1; color: #64748b; font-size: 7.5px; }
        .footer-left { display: inline-block; width: 72%; }
        .footer-right { display: inline-block; width: 27%; text-align: right; }
        @media print {
            .actions { display: none; }
            body { font-size: 9px; }
        }
    </style>
</head>
<body>
<div class="report">
    @unless($pdf)
        <div class="actions">
            @can('downloadReport', $order)
                <a class="action" href="{{ route('laboratory.orders.report.download', $order) }}">Pakua Majibu</a>
            @endcan
            @can('printReport', $order)
                <a class="action secondary" href="{{ route('laboratory.orders.report.print', $order) }}" target="_blank">Chapisha Majibu</a>
            @endcan
        </div>
    @endunless

    <table class="header-table">
        <tr>
            <td style="width: 12%;">
                @if($logoDataUri)
                    <img class="logo" src="{{ $logoDataUri }}" alt="Facility logo">
                @endif
            </td>
            <td style="width: 53%;">
                <h2 class="facility-name">{{ $facility?->name ?? config('app.name') }}</h2>
                <p class="facility-line">{{ collect([$facility?->physical_address, $facility?->ward, $facility?->district, $facility?->region])->filter()->implode(', ') }}</p>
                <p class="facility-line">
                    {{ $facility?->phone_primary }}
                    @if($facility?->email) · {{ $facility->email }} @endif
                </p>
                @if($facility?->registration_number || $facility?->operating_license_number)
                    <p class="facility-line">Registration/License: {{ $facility?->registration_number ?? $facility?->operating_license_number }}</p>
                @endif
            </td>
            <td class="report-title" style="width: 35%;">
                <h1>LABORATORY RESULTS REPORT</h1>
                <p>{{ $order->report_number }}</p>
                @if((int) $order->report_revision > 1)
                    <p><strong>Revision {{ $order->report_revision }}</strong></p>
                @endif
            </td>
        </tr>
    </table>
    <hr class="rule">

    <h2 class="section-title">Patient Details</h2>
    <table class="details-table">
        <tr>
            <td><span class="label">Patient name</span><span class="value">{{ $order->patient?->fullName() }}</span></td>
            <td><span class="label">Patient number</span><span class="value">{{ $order->patient?->patient_number }}</span></td>
            <td><span class="label">Visit number</span><span class="value">{{ $order->visit?->visit_number }}</span></td>
            <td><span class="label">Age / Sex</span><span class="value">{{ $order->patient?->ageLabel() }} / {{ $order->patient?->gender?->label() }}</span></td>
        </tr>
        <tr>
            <td><span class="label">Phone</span><span class="value">{{ $order->patient?->primary_phone ?? '—' }}</span></td>
            <td><span class="label">Patient type</span><span class="value">{{ $order->visit?->payer_type?->label() ?? '—' }}</span></td>
            <td><span class="label">Visit source</span><span class="value">{{ $order->isDirectLaboratory() ? 'Direct Laboratory' : 'OPD Laboratory' }}</span></td>
            <td><span class="label">Date registered</span><span class="value">{{ $order->visit?->registered_at?->format('d/m/Y H:i') ?? '—' }}</span></td>
        </tr>
        <tr>
            <td><span class="label">Sample collected</span><span class="value">{{ $order->samples->min('collected_at')?->format('d/m/Y H:i') ?? '—' }}</span></td>
            <td><span class="label">Results released</span><span class="value">{{ $results->max('released_at')?->format('d/m/Y H:i') ?? '—' }}</span></td>
            <td colspan="2"><span class="label">Report generated</span><span class="value">{{ $order->report_generated_at?->format('d/m/Y H:i') }}</span></td>
        </tr>
    </table>

    <h2 class="section-title">Laboratory Order Details</h2>
    <table class="details-table">
        <tr>
            <td><span class="label">Order number</span><span class="value">{{ $order->order_number }}</span></td>
            <td><span class="label">Ordered by</span><span class="value">{{ $order->orderingClinician?->fullStaffName() ?? 'Reception' }}</span></td>
            <td><span class="label">Source</span><span class="value">{{ $order->isDirectLaboratory() ? 'Reception / Direct Laboratory' : 'OPD' }}</span></td>
            <td><span class="label">Payment</span><span class="value">{{ str($order->payment_status?->value ?? '—')->replace('_', ' ')->title() }}</span></td>
        </tr>
        <tr>
            <td><span class="label">Sample collection</span><span class="value">Completed</span></td>
            <td><span class="label">Result status</span><span class="value">Released</span></td>
            <td><span class="label">Verification status</span><span class="value">Verified</span></td>
            <td><span class="label">Order status</span><span class="value">{{ str($order->status?->value ?? '—')->replace('_', ' ')->title() }}</span></td>
        </tr>
    </table>

    <h2 class="section-title">Test Results</h2>
    <table class="results">
        <thead>
            <tr>
                <th class="center" style="width: 5%;">S/N</th>
                <th style="width: 25%;">Test / Parameter</th>
                <th style="width: 16%;">Result</th>
                <th style="width: 10%;">Unit</th>
                <th style="width: 17%;">Reference Range</th>
                <th style="width: 9%;">Flag</th>
                <th style="width: 18%;">Interpretation / Comment</th>
            </tr>
        </thead>
        <tbody>
            @foreach($results as $result)
                <tr class="test-row">
                    <td class="center">{{ $loop->iteration }}</td>
                    <td colspan="6">{{ $result->test?->name ?? $result->orderItem?->test_name_snapshot }}</td>
                </tr>
                @forelse($result->values->sortBy('sort_order') as $value)
                    <tr>
                        <td></td>
                        <td>{{ $value->parameter_name_snapshot }}</td>
                        <td class="narrative"><strong>{{ $value->displayValue() }}</strong></td>
                        <td>{{ $value->unit_snapshot ?: '—' }}</td>
                        <td>{{ $value->reference_range_snapshot ?: '—' }}</td>
                        <td>{{ $value->abnormal_flag?->value ? str($value->abnormal_flag->value)->title() : '—' }}</td>
                        <td class="narrative">{{ $value->comments ?: ($result->interpretation ?: $result->comments) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td></td>
                        <td>{{ $result->test?->name ?? 'Result' }}</td>
                        <td colspan="4" class="narrative"><strong>{{ $result->overall_result }}</strong></td>
                        <td class="narrative">{{ $result->interpretation ?: $result->comments }}</td>
                    </tr>
                @endforelse
            @endforeach
        </tbody>
    </table>

    <h2 class="section-title">Verification Information</h2>
    @foreach($results as $result)
        <table class="verification-table" style="margin-bottom: 5px;">
            <tr>
                <td>
                    <span class="label">{{ $result->test?->name }} · Result entered by</span>
                    <span class="value">{{ $result->enterer?->fullStaffName() ?? '—' }}</span>
                    <span class="muted">{{ $result->entered_at?->format('d/m/Y H:i') ?? '—' }}</span>
                </td>
                <td>
                    <span class="label">Verified by</span>
                    <span class="value">{{ $result->verifier?->fullStaffName() ?? '—' }}</span>
                    <span class="muted">{{ $result->verified_at?->format('d/m/Y H:i') ?? '—' }}</span>
                </td>
                <td>
                    <span class="label">Released by</span>
                    <span class="value">{{ $result->releaser?->fullStaffName() ?? '—' }}</span>
                    <span class="muted">{{ $result->released_at?->format('d/m/Y H:i') ?? '—' }}</span>
                </td>
            </tr>
        </table>
    @endforeach

    @php($authorized = $results->sortByDesc('released_at')->first())
    <div style="margin-top: 14px;">
        @if($signatureDataUris[$authorized?->id] ?? null)
            <img class="signature" src="{{ $signatureDataUris[$authorized->id] }}" alt="Authorized signature">
        @else
            <div class="signature-line"></div>
        @endif
        <strong>{{ $authorized?->verifier?->fullStaffName() ?? 'Authorized Laboratory Officer' }}</strong><br>
        <span class="muted">{{ $authorized?->verifier?->staffProfile?->employmentRecord?->jobTitle?->name ?? 'Laboratory Officer' }}</span><br>
        <span class="muted">Authorized signature</span>
    </div>

    <div class="footer">
        <span class="footer-left">These results should be interpreted together with the patient’s clinical condition.</span>
        <span class="footer-right">{{ $order->report_number }} · Generated {{ now()->format('d/m/Y H:i') }}</span>
    </div>
</div>
</body>
</html>
