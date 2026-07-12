<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCurrentFacility;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['facility_id','patient_id','membership_id','visit_id','insurance_provider_id','insurance_scheme_id','authorization_number','authorization_type','requested_at','requested_by','requested_amount','approved_amount','status','valid_from','valid_to','response_date','approved_by_name','provider_reference','request_notes','response_notes','attachment_path','created_by','updated_by'])]
class InsurancePreAuthorization extends Model
{
    use BelongsToCurrentFacility, HasFactory, SoftDeletes;
    protected function casts(): array { return ['requested_at'=>'datetime','requested_amount'=>'decimal:2','approved_amount'=>'decimal:2','valid_from'=>'date','valid_to'=>'date','response_date'=>'date']; }
    public function membership(): BelongsTo { return $this->belongsTo(PatientInsuranceMembership::class, 'membership_id'); }
    public function patient(): BelongsTo { return $this->belongsTo(Patient::class); }
}
