<!DOCTYPE html>
<html lang="sw">
<head><meta charset="utf-8"><title>{{ $encounter->encounter_number }}</title><style>body{font-family:Arial,sans-serif;color:#111} .row{display:grid;grid-template-columns:1fr 1fr;gap:16px}.box{border:1px solid #ddd;padding:12px;margin-bottom:12px} h1,h2{margin:0 0 8px} table{width:100%;border-collapse:collapse}td,th{border:1px solid #ddd;padding:6px;text-align:left}@media print{button{display:none}}</style></head>
<body>
<button onclick="window.print()">Print</button>
<h1>{{ $facility?->name ?? 'Facility' }}</h1>
<p>{{ $facility?->address }} · {{ $facility?->phone }}</p>
<h2>Consultation Summary</h2>
<div class="row">
    <div class="box"><strong>Patient:</strong> {{ $encounter->patient->fullName() }}<br><strong>Number:</strong> {{ $encounter->patient->patient_number }}<br><strong>Age/Gender:</strong> {{ $encounter->patient->ageLabel() }} / {{ $encounter->patient->gender->value }}</div>
    <div class="box"><strong>Visit:</strong> {{ $encounter->visit->visit_number }}<br><strong>Encounter:</strong> {{ $encounter->encounter_number }}<br><strong>Provider:</strong> {{ $encounter->provider?->name }}<br><strong>Date:</strong> {{ $encounter->started_at?->format('d/m/Y H:i') }}</div>
</div>
<div class="box"><h3>Chief Complaint</h3><p>{{ $encounter->chief_complaint }}</p></div>
<div class="box"><h3>History</h3><p>{{ $encounter->history_of_presenting_illness }}</p></div>
<div class="box"><h3>Vitals</h3>@php($t=$encounter->visit->latestTriageAssessment)<p>Temp {{ $t?->temperature }} · BP {{ $t?->systolic_bp }}/{{ $t?->diastolic_bp }} · Pulse {{ $t?->pulse_rate }} · SpO2 {{ $t?->oxygen_saturation }}</p></div>
<div class="box"><h3>Examination</h3><p>{{ $encounter->physical_examination }}</p>@foreach($encounter->examinations as $exam)<p><strong>{{ $exam->examination_system }}:</strong> {{ $exam->findings }}</p>@endforeach</div>
<div class="box"><h3>Diagnoses</h3><table><tbody>@foreach($encounter->diagnoses as $diagnosis)<tr><td>{{ $diagnosis->icd10_code }}</td><td>{{ $diagnosis->diagnosis_name }}</td><td>{{ $diagnosis->diagnosis_type->value }}</td></tr>@endforeach</tbody></table></div>
<div class="box"><h3>Orders and Prescriptions</h3><p>Lab orders: {{ $encounter->laboratoryOrders->pluck('order_number')->implode(', ') }}</p><p>Prescriptions: {{ $encounter->prescriptions->pluck('prescription_number')->implode(', ') }}</p><p>Procedures: {{ $encounter->procedureOrders->pluck('procedure_name_snapshot')->implode(', ') }}</p></div>
<div class="box"><h3>Treatment Plan</h3><p>{{ $encounter->treatment_plan }}</p><p>{{ $encounter->discharge_instructions }}</p></div>
<div class="row"><div class="box">Provider signature: __________________</div><div class="box">Facility stamp: __________________</div></div>
</body>
</html>
