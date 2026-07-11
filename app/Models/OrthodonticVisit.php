<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['orthodontic_case_id','dental_encounter_id','visit_date','visit_type','procedure_done','appliance_status','wire_details','elastic_details','oral_hygiene_status','next_visit_date','provider_user_id','notes'])]
class OrthodonticVisit extends Model
{
    use HasFactory, SoftDeletes;
    protected function casts(): array { return ['visit_date'=>'date','next_visit_date'=>'date']; }
    public function case(): BelongsTo { return $this->belongsTo(OrthodonticCase::class, 'orthodontic_case_id'); }
    public function provider(): BelongsTo { return $this->belongsTo(User::class, 'provider_user_id'); }
}
