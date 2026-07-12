<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCurrentFacility;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['facility_id','insurance_provider_id','insurance_scheme_id','benefit_package_id','rule_scope','service_id','service_category_id','medicine_id','department_id','diagnosis_code','coverage_status','coverage_percentage','patient_copayment_type','patient_copayment_value','maximum_quantity','maximum_amount','maximum_visits','requires_pre_authorization','requires_referral','waiting_period_days','effective_from','effective_to','exclusion_reason','notes','priority','is_active'])]
class InsuranceCoverageRule extends Model
{
    use BelongsToCurrentFacility, HasFactory, SoftDeletes;
    protected function casts(): array { return ['coverage_percentage'=>'decimal:2','patient_copayment_value'=>'decimal:2','maximum_quantity'=>'decimal:3','maximum_amount'=>'decimal:2','requires_pre_authorization'=>'boolean','requires_referral'=>'boolean','effective_from'=>'date','effective_to'=>'date','is_active'=>'boolean']; }
    public function provider(): BelongsTo { return $this->belongsTo(InsuranceProvider::class, 'insurance_provider_id'); }
    public function scheme(): BelongsTo { return $this->belongsTo(InsuranceScheme::class, 'insurance_scheme_id'); }
    public function service(): BelongsTo { return $this->belongsTo(Service::class); }
}
