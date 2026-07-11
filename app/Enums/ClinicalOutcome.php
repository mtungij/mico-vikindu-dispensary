<?php

namespace App\Enums;

enum ClinicalOutcome: string
{
    case DischargedHome = 'discharged_home';
    case FollowUp = 'follow_up';
    case Referred = 'referred';
    case AdmittedBedRest = 'admitted_bed_rest';
    case Transferred = 'transferred';
    case LeftAgainstAdvice = 'left_against_advice';
    case Deceased = 'deceased';
    case Ongoing = 'ongoing';
}
