<?php

namespace App\Enums;

enum ObservationDischargeType: string { case Home = 'home'; case Referred = 'referred'; case Transferred = 'transferred'; case LeftAgainstMedicalAdvice = 'left_against_medical_advice'; case Absconded = 'absconded'; case Deceased = 'deceased'; case Other = 'other'; }
