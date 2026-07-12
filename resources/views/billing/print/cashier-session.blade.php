<!doctype html>
<html>
<head><meta charset="utf-8"><title>{{ $session->session_number }}</title><style>body{font-family:Arial,sans-serif;color:#0f172a}table{width:100%;border-collapse:collapse}td,th{border-bottom:1px solid #e2e8f0;padding:8px;text-align:left}.right{text-align:right}.muted{color:#64748b}</style></head>
<body onload="window.print()">
<h2>{{ $facility?->name }}</h2>
<p class="muted">Cashier Session: {{ $session->session_number }}</p>
<p>Cashier: {{ $session->cashier?->name }} | Status: {{ $session->status }} | Expected Cash: {{ number_format($expected, 2) }}</p>
<table><thead><tr><th>Payment</th><th>Invoice</th><th>Method</th><th class="right">Amount</th></tr></thead><tbody>@foreach($session->payments as $payment)<tr><td>{{ $payment->payment_number }}</td><td>{{ $payment->invoice?->invoice_number }}</td><td>{{ $payment->method?->name }}</td><td class="right">{{ number_format($payment->amount, 2) }}</td></tr>@endforeach</tbody></table>
</body>
</html>
