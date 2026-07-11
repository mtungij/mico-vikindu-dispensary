<?php

namespace App\Enums;

enum ObservationAdmissionStatus: string { case Draft = 'draft'; case AwaitingPayment = 'awaiting_payment'; case AwaitingBed = 'awaiting_bed'; case Admitted = 'admitted'; case UnderObservation = 'under_observation'; case ReadyForDischarge = 'ready_for_discharge'; case Discharged = 'discharged'; case Referred = 'referred'; case Transferred = 'transferred'; case LeftAgainstMedicalAdvice = 'left_against_medical_advice'; case Absconded = 'absconded'; case Deceased = 'deceased'; case Cancelled = 'cancelled'; }
