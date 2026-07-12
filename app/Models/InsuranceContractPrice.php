<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCurrentFacility;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['facility_id','insurance_provider_id','insurance_scheme_id','benefit_package_id','service_id','price','patient_amount','payer_amount','effective_from','effective_to','authorization_required','notes','is_active'])]
class InsuranceContractPrice extends Model
{
    use BelongsToCurrentFacility, HasFactory, SoftDeletes;
    protected function casts(): array { return ['price'=>'decimal:2','patient_amount'=>'decimal:2','payer_amount'=>'decimal:2','effective_from'=>'date','effective_to'=>'date','authorization_required'=>'boolean','is_active'=>'boolean']; }
    public function provider(): BelongsTo { return $this->belongsTo(InsuranceProvider::class, 'insurance_provider_id'); }
    public function service(): BelongsTo { return $this->belongsTo(Service::class); }
}
