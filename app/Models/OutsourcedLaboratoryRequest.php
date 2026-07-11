<?php

namespace App\Models;

use App\Enums\OutsourcedLaboratoryStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['facility_id', 'laboratory_order_item_id', 'external_provider_name', 'external_reference_number', 'sent_at', 'expected_at', 'received_at', 'status', 'result_document_path', 'notes', 'created_by', 'updated_by'])]
class OutsourcedLaboratoryRequest extends Model
{
    use HasFactory, SoftDeletes;
    protected function casts(): array { return ['status' => OutsourcedLaboratoryStatus::class, 'sent_at' => 'datetime', 'expected_at' => 'datetime', 'received_at' => 'datetime']; }
    public function scopeForCurrentFacility(Builder $query): Builder { return $query->where('facility_id', currentFacility()?->id); }
    public function orderItem(): BelongsTo { return $this->belongsTo(LaboratoryOrderItem::class, 'laboratory_order_item_id'); }
}
