<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Patient Card - {{ $patient->patient_number }}</title>
    @include('patients.partials.card-styles')
    <style>
        @page { size: 53.98mm 85.60mm; margin: 0; }
        html, body { width: 53.98mm; height: 85.60mm; margin: 0; padding: 0; overflow: hidden; background: #fff; }
        .patient-id-card { width: 53.98mm; height: 85.60mm; max-width: none; border: 0; border-radius: 0; box-shadow: none; }
        @media print {
            html, body { width: 53.98mm !important; height: 85.60mm !important; }
            .patient-id-card { width: 53.98mm !important; height: 85.60mm !important; }
        }
    </style>
</head>
<body onload="window.print()">
    @include('patients.partials.identity-card')
</body>
</html>
