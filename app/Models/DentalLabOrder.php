<?php

namespace App\Models;

use App\Enums\DentalLabOrderStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['facility_id','patient_id','visit_id','dental_encounter_id','treatment_plan_item_id','order_number','work_type','tooth_numbers','shade','material','design_instructions','external_lab_name','external_reference','sent_at','expected_at','received_at','fitted_at','status','ordered_by','created_by','updated_by'])]
class DentalLabOrder extends Model
{
    use HasFactory, SoftDeletes;
    protected function casts(): array { return ['status'=>DentalLabOrderStatus::class,'tooth_numbers'=>'array','sent_at'=>'datetime','expected_at'=>'datetime','received_at'=>'datetime','fitted_at'=>'datetime']; }
    public function scopeForCurrentFacility(Builder $q): Builder { return $q->where('facility_id', currentFacility()?->id); }
    public function encounter(): BelongsTo { return $this->belongsTo(DentalEncounter::class, 'dental_encounter_id'); }
    public function patient(): BelongsTo { return $this->belongsTo(Patient::class); }
}
