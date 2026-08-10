<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['prescription_id', 'medicine_id', 'medication_name', 'generic_name', 'strength', 'dosage_form', 'dose', 'route', 'frequency', 'duration_value', 'duration_unit', 'quantity', 'dispensed_quantity', 'remaining_quantity', 'substitution_medicine_id', 'substitution_reason', 'dispensing_status', 'unit_price_snapshot', 'patient_amount', 'insurance_amount', 'payer_amount', 'instructions', 'indication', 'substitution_allowed', 'status', 'terminal_status', 'terminal_reason', 'terminal_at', 'terminal_by', 'service_id', 'invoice_item_id', 'created_by', 'updated_by'])]
class PrescriptionItem extends Model
{
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return ['substitution_allowed' => 'boolean', 'quantity' => 'decimal:2', 'dispensed_quantity' => 'decimal:3', 'remaining_quantity' => 'decimal:3', 'unit_price_snapshot' => 'decimal:2', 'patient_amount' => 'decimal:2', 'insurance_amount' => 'decimal:2', 'payer_amount' => 'decimal:2', 'terminal_at' => 'datetime'];
    }

    public function prescription(): BelongsTo
    {
        return $this->belongsTo(Prescription::class);
    }

    public function medicine(): BelongsTo
    {
        return $this->belongsTo(Medicine::class);
    }

    public function substitutionMedicine(): BelongsTo
    {
        return $this->belongsTo(Medicine::class, 'substitution_medicine_id');
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function invoiceItem(): BelongsTo
    {
        return $this->belongsTo(InvoiceItem::class);
    }
}
