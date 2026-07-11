<?php

namespace App\Models;

use App\Enums\LaboratoryQualityStatus;
use App\Enums\LaboratorySampleStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['facility_id', 'laboratory_order_id', 'patient_id', 'visit_id', 'sample_number', 'barcode_value', 'specimen_type_id', 'container_type', 'collected_by', 'collected_at', 'received_by', 'received_at', 'collection_location', 'volume_collected', 'volume_unit', 'collection_notes', 'sample_status', 'quality_status', 'rejection_reason_id', 'rejection_notes', 'rejected_by', 'rejected_at', 'disposed_by', 'disposed_at', 'expiry_at', 'created_by', 'updated_by'])]
class LaboratorySample extends Model
{
    use HasFactory, SoftDeletes;
    protected function casts(): array { return ['sample_status' => LaboratorySampleStatus::class, 'quality_status' => LaboratoryQualityStatus::class, 'collected_at' => 'datetime', 'received_at' => 'datetime', 'rejected_at' => 'datetime', 'disposed_at' => 'datetime', 'expiry_at' => 'datetime', 'volume_collected' => 'decimal:2']; }
    public function scopeForCurrentFacility(Builder $query): Builder { return $query->where('facility_id', currentFacility()?->id); }
    public function order(): BelongsTo { return $this->belongsTo(LaboratoryOrder::class, 'laboratory_order_id'); }
    public function specimenType(): BelongsTo { return $this->belongsTo(SpecimenType::class); }
    public function items(): HasMany { return $this->hasMany(LaboratorySampleItem::class); }
    public function results(): HasMany { return $this->hasMany(LaboratoryResult::class); }
}
