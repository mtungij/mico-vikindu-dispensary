<?php

return [
    'adult' => [
        'temperature' => ['low' => 35.0, 'high' => 38.0, 'critical_high' => 39.5],
        'systolic_bp' => ['low' => 90, 'high' => 140, 'critical_high' => 180],
        'diastolic_bp' => ['low' => 60, 'high' => 90, 'critical_high' => 120],
        'pulse_rate' => ['low' => 50, 'high' => 100, 'critical_high' => 130],
        'respiratory_rate' => ['low' => 10, 'high' => 22, 'critical_high' => 30],
        'oxygen_saturation' => ['low' => 94, 'critical_low' => 90],
        'blood_glucose' => ['low' => 3.9, 'high' => 11.1, 'critical_high' => 16.7],
    ],
    'pregnancy' => [
        'systolic_bp_urgent' => 140,
        'diastolic_bp_urgent' => 90,
        'gestational_age_high_risk' => 28,
    ],
    'disclaimer' => 'Tahadhari hizi ni msaada wa kliniki na hazibadilishi uamuzi wa mtoa huduma.',
];
