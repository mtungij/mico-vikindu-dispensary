<!doctype html>
<html>
<head><meta charset="utf-8"><title>{{ $invoice->invoice_number }}</title><style>body{font-family:Arial,sans-serif;color:#0f172a}table{width:100%;border-collapse:collapse}td,th{border-bottom:1px solid #e2e8f0;padding:8px;text-align:left}.right{text-align:right}.muted{color:#64748b}</style></head>
<body onload="window.print()">
<h2>{{ $facility?->name }}</h2>
<p class="muted">Invoice: {{ $invoice->invoice_number }} | Date: {{ $invoice->issued_at?->format('Y-m-d H:i') }}</p>
<p>Patient: {{ $invoice->patient?->first_name }} {{ $invoice->patient?->last_name }}</p>
<table><thead><tr><th>Item</th><th class="right">Qty</th><th class="right">Total</th></tr></thead><tbody>@foreach($invoice->items as $item)<tr><td>{{ $item->description_snapshot ?? $item->description }}</td><td class="right">{{ $item->quantity }}</td><td class="right">{{ number_format($item->total_amount, 2) }}</td></tr>@endforeach</tbody></table>
<h3 class="right">Balance: {{ number_format($invoice->balance_amount, 2) }}</h3>
</body>
</html>
