<?php

namespace App\Enums;

enum FacilityDocumentType: string
{
    case RegistrationCertificate = 'registration_certificate';
    case OperatingLicense = 'operating_license';
    case NhifAccreditation = 'nhif_accreditation';
    case TinCertificate = 'tin_certificate';
    case BusinessLicense = 'business_license';
    case OwnershipDocument = 'ownership_document';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::RegistrationCertificate => 'Registration Certificate',
            self::OperatingLicense => 'Operating License',
            self::NhifAccreditation => 'NHIF Accreditation',
            self::TinCertificate => 'TIN Certificate',
            self::BusinessLicense => 'Business License',
            self::OwnershipDocument => 'Ownership Document',
            self::Other => 'Nyingine',
        };
    }
}
