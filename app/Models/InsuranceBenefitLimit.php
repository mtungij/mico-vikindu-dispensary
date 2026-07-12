<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCurrentFacility;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['facility_id','benefit_package_id','benefit_type','limit_type','limit_amount','limit_quantity','period_type','service_id','service_category_id','medicine_id','department_id','max_visits','max_days','requires_authorization','requires_referral','notes','is_active'])]
class InsuranceBenefitLimit extends Model
{
    use BelongsToCurrentFacility, HasFactory, SoftDeletes;
    protected function casts(): array { return ['limit_amount'=>'decimal:2','limit_quantity'=>'decimal:3','requires_authorization'=>'boolean','requires_referral'=>'boolean','is_active'=>'boolean']; }
    public function package(): BelongsTo { return $this->belongsTo(InsuranceBenefitPackage::class, 'benefit_package_id'); }
    public function service(): BelongsTo { return $this->belongsTo(Service::class); }
    public function medicine(): BelongsTo { return $this->belongsTo(Medicine::class); }
}
