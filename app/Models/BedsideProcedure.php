<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['facility_id','observation_admission_id','patient_id','visit_id','observation_order_id','service_id','procedure_name_snapshot','performed_by','assisted_by','performed_at','status','findings','materials_used','complications','notes','invoice_item_id','created_by','updated_by'])]
class BedsideProcedure extends Model { use HasFactory, SoftDeletes; protected function casts(): array { return ['performed_at'=>'datetime','materials_used'=>'array']; } public function scopeForCurrentFacility(Builder $q): Builder { return $q->where('facility_id', currentFacility()?->id); } public function admission(): BelongsTo { return $this->belongsTo(ObservationAdmission::class, 'observation_admission_id'); } public function service(): BelongsTo { return $this->belongsTo(Service::class); } public function invoiceItem(): BelongsTo { return $this->belongsTo(InvoiceItem::class); } }
