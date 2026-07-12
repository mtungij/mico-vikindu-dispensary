<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCurrentFacility;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['facility_id','insurance_provider_id','insurance_scheme_id','name','code','description','annual_limit','visit_limit','inpatient_limit','outpatient_limit','dental_limit','pharmacy_limit','laboratory_limit','rch_limit','observation_limit','effective_from','effective_to','is_active','created_by','updated_by'])]
class InsuranceBenefitPackage extends Model
{
    use BelongsToCurrentFacility, HasFactory, SoftDeletes;
    protected function casts(): array { return ['annual_limit'=>'decimal:2','visit_limit'=>'decimal:2','inpatient_limit'=>'decimal:2','outpatient_limit'=>'decimal:2','dental_limit'=>'decimal:2','pharmacy_limit'=>'decimal:2','laboratory_limit'=>'decimal:2','rch_limit'=>'decimal:2','observation_limit'=>'decimal:2','effective_from'=>'date','effective_to'=>'date','is_active'=>'boolean']; }
    public function provider(): BelongsTo { return $this->belongsTo(InsuranceProvider::class, 'insurance_provider_id'); }
    public function scheme(): BelongsTo { return $this->belongsTo(InsuranceScheme::class, 'insurance_scheme_id'); }
    public function limits(): HasMany { return $this->hasMany(InsuranceBenefitLimit::class, 'benefit_package_id'); }
}
