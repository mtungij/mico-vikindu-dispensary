<!doctype html>
<html>
<head><meta charset="utf-8"><title>{{ $receipt->receipt_number }}</title><style>body{font-family:Arial,sans-serif;color:#0f172a}.box{max-width:420px}.row{display:flex;justify-content:space-between;border-bottom:1px solid #e2e8f0;padding:8px 0}.muted{color:#64748b}</style></head>
<body onload="window.print()">
<div class="box">
<h2>{{ $facility?->name }}</h2>
<p class="muted">Receipt: {{ $receipt->receipt_number }}</p>
<div class="row"><span>Invoice</span><strong>{{ $receipt->invoice?->invoice_number }}</strong></div>
<div class="row"><span>Patient</span><strong>{{ $receipt->invoice?->patient?->first_name }} {{ $receipt->invoice?->patient?->last_name }}</strong></div>
<div class="row"><span>Method</span><strong>{{ $receipt->payment_method_snapshot }}</strong></div>
<div class="row"><span>Reference</span><strong>{{ $receipt->transaction_reference_snapshot }}</strong></div>
<div class="row"><span>Cashier</span><strong>{{ $receipt->cashier_name_snapshot }}</strong></div>
<div class="row"><span>Amount</span><strong>{{ number_format($receipt->amount, 2) }}</strong></div>
</div>
</body>
</html>
