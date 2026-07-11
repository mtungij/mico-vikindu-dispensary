<?php

namespace App\Enums;

enum StaffDocumentType: string
{
    case CurriculumVitae = 'curriculum_vitae';
    case Nida = 'nida';
    case Passport = 'passport';
    case BirthCertificate = 'birth_certificate';
    case AcademicCertificate = 'academic_certificate';
    case ProfessionalCertificate = 'professional_certificate';
    case PracticingLicense = 'practicing_license';
    case EmploymentContract = 'employment_contract';
    case AppointmentLetter = 'appointment_letter';
    case RecommendationLetter = 'recommendation_letter';
    case PoliceClearance = 'police_clearance';
    case TaxDocument = 'tax_document';
    case Signature = 'signature';
    case Other = 'other';

    public function label(): string
    {
        return str($this->value)->replace('_', ' ')->title()->toString();
    }
}
