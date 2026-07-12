<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCurrentFacility;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['facility_id','patient_id','insurance_provider_id','insurance_scheme_id','benefit_package_id','membership_plan_id','membership_number','principal_member_number','membership_type','principal_patient_id','employer_name','employer_number','valid_from','valid_to','verification_status','last_verified_at','verification_method','verification_reference','verification_notes','card_front_path','card_back_path','is_primary','is_active','created_by','updated_by'])]
class PatientInsuranceMembership extends Model
{
    use BelongsToCurrentFacility, HasFactory, SoftDeletes;
    protected function casts(): array { return ['valid_from'=>'date','valid_to'=>'date','last_verified_at'=>'datetime','is_primary'=>'boolean','is_active'=>'boolean']; }
    public function patient(): BelongsTo { return $this->belongsTo(Patient::class); }
    public function provider(): BelongsTo { return $this->belongsTo(InsuranceProvider::class, 'insurance_provider_id'); }
    public function scheme(): BelongsTo { return $this->belongsTo(InsuranceScheme::class, 'insurance_scheme_id'); }
    public function benefitPackage(): BelongsTo { return $this->belongsTo(InsuranceBenefitPackage::class, 'benefit_package_id'); }
    public function plan(): BelongsTo { return $this->belongsTo(InsuranceMembershipPlan::class, 'membership_plan_id'); }
    public function dependants(): HasMany { return $this->hasMany(InsuranceDependant::class, 'principal_membership_id'); }
    public function verifications(): HasMany { return $this->hasMany(InsuranceVerification::class, 'membership_id'); }
}
