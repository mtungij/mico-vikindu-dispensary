<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCurrentFacility;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['facility_id','patient_id','membership_id','visit_id','verification_type','verification_method','status','verified_at','valid_until','reference_number','response_summary','verified_by','override_reason','metadata'])]
class InsuranceVerification extends Model
{
    use BelongsToCurrentFacility, HasFactory;
    protected function casts(): array { return ['verified_at'=>'datetime','valid_until'=>'datetime','metadata'=>'array']; }
    public function membership(): BelongsTo { return $this->belongsTo(PatientInsuranceMembership::class, 'membership_id'); }
    public function patient(): BelongsTo { return $this->belongsTo(Patient::class); }
}
