<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['invoice_id', 'service_id', 'insurance_provider_id', 'insurance_scheme_id', 'patient_insurance_membership_id', 'insurance_pre_authorization_id', 'insurance_referral_id', 'item_type', 'description', 'quantity', 'unit_price', 'discount_amount', 'tax_amount', 'total_amount', 'payer_amount', 'insurance_amount', 'patient_amount', 'coverage_percentage', 'claimable_status', 'status', 'metadata', 'created_by'])]
class InvoiceItem extends Model
{
    use HasFactory, SoftDeletes;
    protected function casts(): array { return ['quantity' => 'decimal:2', 'unit_price' => 'decimal:2', 'total_amount' => 'decimal:2', 'payer_amount' => 'decimal:2', 'insurance_amount' => 'decimal:2', 'patient_amount' => 'decimal:2', 'coverage_percentage' => 'decimal:2', 'metadata' => 'array']; }
    public function invoice(): BelongsTo { return $this->belongsTo(Invoice::class); }
    public function service(): BelongsTo { return $this->belongsTo(Service::class); }
    public function insuranceProvider(): BelongsTo { return $this->belongsTo(InsuranceProvider::class); }
    public function insuranceScheme(): BelongsTo { return $this->belongsTo(InsuranceScheme::class); }
    public function insuranceMembership(): BelongsTo { return $this->belongsTo(PatientInsuranceMembership::class, 'patient_insurance_membership_id'); }
}
