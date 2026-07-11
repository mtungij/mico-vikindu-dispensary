<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['laboratory_order_id', 'service_id', 'laboratory_test_id', 'specimen_type_id', 'sample_id', 'test_name_snapshot', 'test_code_snapshot', 'unit_price_snapshot', 'payer_amount', 'insurance_amount', 'patient_amount', 'priority', 'status', 'result_status', 'result_entered_at', 'result_verified_at', 'result_released_at', 'specimen_type', 'notes', 'invoice_item_id', 'created_by'])]
class LaboratoryOrderItem extends Model
{
    use HasFactory, SoftDeletes;
    protected function casts(): array { return ['result_entered_at' => 'datetime', 'result_verified_at' => 'datetime', 'result_released_at' => 'datetime']; }
    public function order(): BelongsTo { return $this->belongsTo(LaboratoryOrder::class, 'laboratory_order_id'); }
    public function service(): BelongsTo { return $this->belongsTo(Service::class); }
    public function laboratoryTest(): BelongsTo { return $this->belongsTo(LaboratoryTest::class); }
    public function specimenType(): BelongsTo { return $this->belongsTo(SpecimenType::class); }
    public function sample(): BelongsTo { return $this->belongsTo(LaboratorySample::class, 'sample_id'); }
    public function results() { return $this->hasMany(LaboratoryResult::class); }
}
