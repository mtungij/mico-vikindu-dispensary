<!DOCTYPE html>
<html lang="sw">
<head><meta charset="utf-8"><title>{{ $referral->referral_number }}</title><style>body{font-family:Arial,sans-serif;color:#111}.box{border:1px solid #ddd;padding:12px;margin-bottom:12px}.row{display:grid;grid-template-columns:1fr 1fr;gap:16px}@media print{button{display:none}}</style></head>
<body>
<button onclick="window.print()">Print</button>
<h1>{{ $facility?->name ?? 'Facility' }}</h1>
<p>{{ $facility?->address }} · {{ $facility?->phone }}</p>
<h2>Referral Form</h2>
<div class="row"><div class="box"><strong>Patient:</strong> {{ $referral->patient->fullName() }}<br><strong>Number:</strong> {{ $referral->patient->patient_number }}<br><strong>Visit:</strong> {{ $referral->visit->visit_number }}</div><div class="box"><strong>Referral:</strong> {{ $referral->referral_number }}<br><strong>Urgency:</strong> {{ $referral->urgency }}<br><strong>Date:</strong> {{ $referral->referred_at?->format('d/m/Y H:i') }}</div></div>
<div class="box"><h3>Destination</h3><p>{{ $referral->destination_facility_name }} · {{ $referral->destination_department }} · {{ $referral->destination_contact }}</p></div>
<div class="box"><h3>Reason</h3><p>{{ $referral->reason }}</p></div>
<div class="box"><h3>Clinical Summary</h3><p>{{ $referral->clinical_summary }}</p></div>
<div class="box"><h3>Diagnosis and Treatment</h3><p>{{ $referral->provisional_diagnosis }}</p><p>{{ $referral->treatment_given }}</p></div>
<div class="box"><h3>Investigations and Medications</h3><p>{{ $referral->investigations_done }}</p><p>{{ $referral->current_medications }}</p></div>
<div class="row"><div class="box">Referrer signature: __________________</div><div class="box">Facility stamp: __________________</div></div>
</body>
</html>
