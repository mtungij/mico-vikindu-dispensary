<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['facility_id', 'invoice_id', 'patient_id', 'visit_id', 'service_id', 'insurance_provider_id', 'insurance_scheme_id', 'patient_insurance_membership_id', 'insurance_pre_authorization_id', 'insurance_referral_id', 'item_type', 'reference_type', 'reference_id', 'code_snapshot', 'description', 'description_snapshot', 'department_id', 'quantity', 'unit_price', 'gross_amount', 'discount_amount', 'waiver_amount', 'tax_amount', 'total_amount', 'payer_amount', 'insurance_amount', 'corporate_amount', 'patient_amount', 'net_amount', 'paid_amount', 'coverage_percentage', 'claimable_status', 'status', 'service_date', 'performed_at', 'cancelled_at', 'cancelled_by', 'cancellation_reason', 'price_snapshot', 'coverage_snapshot', 'metadata', 'created_by', 'updated_by'])]
class InvoiceItem extends Model
{
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return ['quantity' => 'decimal:3', 'unit_price' => 'decimal:2', 'gross_amount' => 'decimal:2', 'discount_amount' => 'decimal:2', 'waiver_amount' => 'decimal:2', 'tax_amount' => 'decimal:2', 'total_amount' => 'decimal:2', 'payer_amount' => 'decimal:2', 'insurance_amount' => 'decimal:2', 'corporate_amount' => 'decimal:2', 'patient_amount' => 'decimal:2', 'net_amount' => 'decimal:2', 'paid_amount' => 'decimal:2', 'coverage_percentage' => 'decimal:2', 'service_date' => 'date', 'performed_at' => 'datetime', 'cancelled_at' => 'datetime', 'price_snapshot' => 'array', 'coverage_snapshot' => 'array', 'metadata' => 'array'];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function insuranceProvider(): BelongsTo
    {
        return $this->belongsTo(InsuranceProvider::class);
    }

    public function insuranceScheme(): BelongsTo
    {
        return $this->belongsTo(InsuranceScheme::class);
    }

    public function insuranceMembership(): BelongsTo
    {
        return $this->belongsTo(PatientInsuranceMembership::class, 'patient_insurance_membership_id');
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function laboratoryOrderItem(): HasOne
    {
        return $this->hasOne(LaboratoryOrderItem::class);
    }
}
