<?php

namespace App\Enums;

enum MovementReason: string
{
    case Registration = 'registration';
    case PaymentRequired = 'payment_required';
    case PaymentCompleted = 'payment_completed';
    case TriageRequired = 'triage_required';
    case TriageCompleted = 'triage_completed';
    case ConsultationStarted = 'consultation_started';
    case LaboratoryOrdered = 'laboratory_ordered';
    case LaboratoryCompleted = 'laboratory_completed';
    case PrescriptionCreated = 'prescription_created';
    case PharmacyCompleted = 'pharmacy_completed';
    case BedAdmission = 'bed_admission';
    case DentalVisit = 'dental_visit';
    case Transfer = 'transfer';
    case EmergencyOverride = 'emergency_override';
    case VisitCompleted = 'visit_completed';
    case VisitCancelled = 'visit_cancelled';
}
