<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCurrentFacility;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['facility_id','insurance_provider_id','insurance_scheme_id','claim_submission_days','correction_submission_days','resubmission_days','minimum_claim_amount','maximum_claim_amount','requires_primary_diagnosis','requires_service_codes','requires_provider_signature','requires_facility_stamp','requires_invoice_attachment','requires_prescription_attachment','requires_lab_report_attachment','requires_referral_attachment','requires_authorization_attachment','other_requirements','effective_from','effective_to','is_active'])]
class InsuranceClaimRule extends Model
{
    use BelongsToCurrentFacility, HasFactory, SoftDeletes;
    protected function casts(): array { return ['minimum_claim_amount'=>'decimal:2','maximum_claim_amount'=>'decimal:2','requires_primary_diagnosis'=>'boolean','requires_service_codes'=>'boolean','requires_provider_signature'=>'boolean','requires_facility_stamp'=>'boolean','requires_invoice_attachment'=>'boolean','requires_prescription_attachment'=>'boolean','requires_lab_report_attachment'=>'boolean','requires_referral_attachment'=>'boolean','requires_authorization_attachment'=>'boolean','other_requirements'=>'array','effective_from'=>'date','effective_to'=>'date','is_active'=>'boolean']; }
}
