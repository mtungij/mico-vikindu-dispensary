<?php

namespace App\Models;

use App\Enums\InvoiceStatus;
use App\Enums\PayerType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['facility_id', 'patient_id', 'visit_id', 'invoice_number', 'invoice_type', 'payer_type', 'patient_payer_profile_id', 'insurance_provider_id', 'corporate_account_id', 'invoice_status', 'subtotal', 'discount_amount', 'waiver_amount', 'tax_amount', 'gross_total', 'patient_amount', 'insurance_amount', 'corporate_amount', 'total_amount', 'paid_amount', 'refunded_amount', 'balance_amount', 'status', 'payment_status', 'currency', 'issued_at', 'due_at', 'finalized_at', 'finalized_by', 'voided_at', 'voided_by', 'void_reason', 'notes', 'created_by', 'updated_by'])]
class Invoice extends Model
{
    use HasFactory, SoftDeletes;
    protected function casts(): array { return ['payer_type' => PayerType::class, 'invoice_status' => InvoiceStatus::class, 'subtotal' => 'decimal:2', 'discount_amount' => 'decimal:2', 'waiver_amount' => 'decimal:2', 'tax_amount' => 'decimal:2', 'gross_total' => 'decimal:2', 'patient_amount' => 'decimal:2', 'insurance_amount' => 'decimal:2', 'corporate_amount' => 'decimal:2', 'total_amount' => 'decimal:2', 'paid_amount' => 'decimal:2', 'refunded_amount' => 'decimal:2', 'balance_amount' => 'decimal:2', 'issued_at' => 'datetime', 'due_at' => 'datetime', 'finalized_at' => 'datetime', 'voided_at' => 'datetime']; }
    public function scopeForCurrentFacility(Builder $query): Builder { return $query->where('facility_id', currentFacility()?->id); }
    public function items(): HasMany { return $this->hasMany(InvoiceItem::class); }
    public function payments(): HasMany { return $this->hasMany(Payment::class); }
    public function receipts(): HasMany { return $this->hasMany(Receipt::class); }
    public function handoffs(): HasMany { return $this->hasMany(VisitPaymentHandoff::class); }
    public function patientPayerProfile(): BelongsTo { return $this->belongsTo(PatientPayerProfile::class); }
    public function visit(): BelongsTo { return $this->belongsTo(Visit::class); }
    public function patient(): BelongsTo { return $this->belongsTo(Patient::class); }
    public function insuranceProvider(): BelongsTo { return $this->belongsTo(InsuranceProvider::class); }
    public function corporateAccount(): BelongsTo { return $this->belongsTo(CorporateAccount::class); }
}
