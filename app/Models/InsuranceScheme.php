<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCurrentFacility;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['facility_id','insurance_provider_id','name','code','description','scheme_type','effective_from','effective_to','default_benefit_package_id','requires_membership_verification','requires_pre_authorization','requires_referral','allows_dependants','allows_copayment','is_active','created_by','updated_by'])]
class InsuranceScheme extends Model
{
    use BelongsToCurrentFacility, HasFactory, SoftDeletes;
    protected function casts(): array { return ['effective_from'=>'date','effective_to'=>'date','requires_membership_verification'=>'boolean','requires_pre_authorization'=>'boolean','requires_referral'=>'boolean','allows_dependants'=>'boolean','allows_copayment'=>'boolean','is_active'=>'boolean']; }
    public function provider(): BelongsTo { return $this->belongsTo(InsuranceProvider::class, 'insurance_provider_id'); }
    public function defaultBenefitPackage(): BelongsTo { return $this->belongsTo(InsuranceBenefitPackage::class, 'default_benefit_package_id'); }
    public function benefitPackages(): HasMany { return $this->hasMany(InsuranceBenefitPackage::class); }
}
