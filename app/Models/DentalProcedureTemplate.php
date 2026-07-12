<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['facility_id','name','code','dental_procedure_type_id','service_id','default_diagnosis','default_anaesthesia_type','default_anaesthetic_id','requires_consent','consent_template_id','default_materials','default_post_op_instructions','default_follow_up_days','send_to_observation','is_active','created_by','updated_by'])]
class DentalProcedureTemplate extends Model
{
    use SoftDeletes;
    protected function casts(): array { return ['default_materials'=>'array','requires_consent'=>'boolean','send_to_observation'=>'boolean','is_active'=>'boolean','default_follow_up_days'=>'integer']; }
    public function scopeForCurrentFacility(Builder $query): Builder { return $query->where('facility_id', currentFacility()?->id); }
    public function procedureType(): BelongsTo { return $this->belongsTo(DentalProcedureType::class, 'dental_procedure_type_id'); }
    public function service(): BelongsTo { return $this->belongsTo(Service::class); }
    public function anaesthetic(): BelongsTo { return $this->belongsTo(DentalAnaestheticType::class, 'default_anaesthetic_id'); }
    public function consentTemplate(): BelongsTo { return $this->belongsTo(DentalConsentTemplate::class, 'consent_template_id'); }
}
