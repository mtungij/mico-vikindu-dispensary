<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCurrentFacility;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['facility_id','insurance_provider_id','insurance_scheme_id','benefit_package_id','name','code','membership_type','waiting_period_days','dependent_limit','age_limit','copayment_type','copayment_value','coinsurance_percentage','deductible_amount','effective_from','effective_to','is_active','created_by','updated_by'])]
class InsuranceMembershipPlan extends Model
{
    use BelongsToCurrentFacility, HasFactory, SoftDeletes;
    protected function casts(): array { return ['copayment_value'=>'decimal:2','coinsurance_percentage'=>'decimal:2','deductible_amount'=>'decimal:2','effective_from'=>'date','effective_to'=>'date','is_active'=>'boolean']; }
    public function provider(): BelongsTo { return $this->belongsTo(InsuranceProvider::class, 'insurance_provider_id'); }
    public function scheme(): BelongsTo { return $this->belongsTo(InsuranceScheme::class, 'insurance_scheme_id'); }
    public function benefitPackage(): BelongsTo { return $this->belongsTo(InsuranceBenefitPackage::class, 'benefit_package_id'); }
}
