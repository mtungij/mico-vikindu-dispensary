<?php

return [
    'title' => 'Hali ya huduma ya OPD',
    'consultation_completed' => 'Consultation Imekamilika',
    'unavailable' => 'Huduma nyingine inaendelea',
    'completed_care' => ':patient tayari amekamilisha consultation ya daktari. Consultation hii haitafunguliwa upya.',
    'completed_follow_up' => ':patient tayari amekamilisha OPD consultation na doctor follow-up.',
    'balance_message' => 'Kuna :balance ambazo bado hazijalipwa.',
    'messages' => [
        'payment' => 'Mgonjwa ana malipo yanayosubiri kukamilishwa.',
        'pharmacy' => 'Consultation imekamilika. Mgonjwa ana dawa zinazosubiri Pharmacy.',
        'laboratory' => 'Mgonjwa anasubiri huduma au majibu ya Laboratory.',
        'procedure' => 'Mgonjwa ana procedure inayosubiri kukamilishwa.',
        'admission' => 'Mgonjwa yupo kwenye huduma ya Observation / Bed.',
        'completed' => 'Visit hii tayari imekamilika.',
        'closed' => 'Visit hii imefungwa. Consultation mpya haiwezi kuanzishwa kwenye visit hii.',
        'consultation_completed' => 'Consultation ya daktari tayari imekamilika.',
        'conflict' => 'Hali ya huduma inahitaji kuhakikiwa kabla ya consultation kufunguliwa.',
    ],
    'actions' => [
        'payment' => 'Kamilisha malipo kwa Cashier kabla ya kuendelea na huduma inayofuata.',
        'pharmacy' => 'Mgonjwa aende Pharmacy kwa huduma ya dawa.',
        'laboratory' => 'Endelea na Laboratory. Matokeo yanayohitaji daktari yatapitiwa kupitia Doctor Review.',
        'procedure' => 'Mgonjwa aende kwenye idara inayotoa procedure.',
        'admission' => 'Endelea na huduma ya Observation / Bed.',
        'completed' => 'Angalia kumbukumbu za mgonjwa. Huduma mpya inahitaji visit mpya.',
        'closed' => 'Angalia kumbukumbu za mgonjwa au wasiliana na mhudumu husika.',
        'consultation_completed' => 'Angalia kumbukumbu za mgonjwa kwa taarifa za consultation iliyokamilika.',
        'conflict' => 'Wasiliana na mhudumu husika ili ahakiki hatua ya huduma.',
    ],
    'destinations' => [
        'payment' => 'Malipo / Cashier', 'pharmacy' => 'Pharmacy', 'laboratory' => 'Laboratory',
        'procedure' => 'Procedure Department', 'admission' => 'Observation / Bed', 'doctor_review' => 'Doctor Review',
        'completed' => 'Kumbukumbu za mgonjwa', 'closed' => 'Kumbukumbu za mgonjwa',
        'consultation_completed' => 'Kumbukumbu za mgonjwa', 'conflict' => 'Uhakiki wa huduma',
    ],
    'statuses' => [
        'payment' => 'Anasubiri malipo', 'pharmacy' => 'Anasubiri dawa', 'laboratory' => 'Anasubiri Laboratory',
        'procedure' => 'Anasubiri procedure', 'admission' => 'Observation / Bed',
        'completed' => 'Visit imekamilika', 'closed' => 'Visit imefungwa',
        'consultation_completed' => 'Consultation imekamilika', 'conflict' => 'Inahitaji uhakiki',
    ],
];
