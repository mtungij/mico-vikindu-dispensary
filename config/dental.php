<?php

return [
    'default_numbering_system' => 'fdi',
    'adult_teeth' => array_map('strval', array_merge(range(11, 18), range(21, 28), range(31, 38), range(41, 48))),
    'primary_teeth' => array_map('strval', array_merge(range(51, 55), range(61, 65), range(71, 75), range(81, 85))),
    'posterior_teeth' => ['14','15','16','17','18','24','25','26','27','28','34','35','36','37','38','44','45','46','47','48','54','55','64','65','74','75','84','85'],
    'surfaces' => [
        'posterior' => ['mesial' => 'M', 'distal' => 'D', 'occlusal' => 'O', 'buccal' => 'B', 'lingual' => 'Li'],
        'anterior' => ['mesial' => 'M', 'distal' => 'D', 'incisal' => 'I', 'labial' => 'La', 'lingual' => 'Li'],
    ],
    'finding_colors' => [
        'healthy' => '#16a34a',
        'caries' => '#dc2626',
        'missing' => '#64748b',
        'filled' => '#2563eb',
        'planned_treatment' => '#f59e0b',
        'completed_treatment' => '#059669',
    ],
];
