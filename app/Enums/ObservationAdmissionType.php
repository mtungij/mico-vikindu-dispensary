<?php

namespace App\Enums;

enum ObservationAdmissionType: string { case HourlyObservation = 'hourly_observation'; case DayCare = 'day_care'; case Overnight = 'overnight'; case ShortStay = 'short_stay'; case PostProcedure = 'post_procedure'; case EmergencyStabilization = 'emergency_stabilization'; case DentalRecovery = 'dental_recovery'; case Other = 'other'; }
