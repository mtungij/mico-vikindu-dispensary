<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCurrentFacility;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['facility_id','principal_membership_id','dependent_patient_id','relationship_type','dependent_membership_number','valid_from','valid_to','verification_status','is_active','created_by','updated_by'])]
class InsuranceDependant extends Model
{
    use BelongsToCurrentFacility, HasFactory, SoftDeletes;
    protected function casts(): array { return ['valid_from'=>'date','valid_to'=>'date','is_active'=>'boolean']; }
    public function membership(): BelongsTo { return $this->belongsTo(PatientInsuranceMembership::class, 'principal_membership_id'); }
    public function patient(): BelongsTo { return $this->belongsTo(Patient::class, 'dependent_patient_id'); }
}
