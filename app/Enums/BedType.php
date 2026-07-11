<?php

namespace App\Enums;

enum BedType: string { case Standard = 'standard'; case Pediatric = 'pediatric'; case Maternity = 'maternity'; case Isolation = 'isolation'; case Recovery = 'recovery'; case Emergency = 'emergency'; case RecliningChair = 'reclining_chair'; case Trolley = 'trolley'; case Other = 'other'; }
