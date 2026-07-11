<?php

namespace App\Enums;

enum ObservationRoomType: string { case GeneralObservation = 'general_observation'; case EmergencyObservation = 'emergency_observation'; case PediatricObservation = 'pediatric_observation'; case FemaleObservation = 'female_observation'; case MaleObservation = 'male_observation'; case MaternityObservation = 'maternity_observation'; case Isolation = 'isolation'; case ProcedureRecovery = 'procedure_recovery'; case DentalRecovery = 'dental_recovery'; case Other = 'other'; }
