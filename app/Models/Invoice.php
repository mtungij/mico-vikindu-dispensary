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

#[Fillable(['facility_id', 'patient_id', 'visit_id', 'invoice_number', 'payer_type', 'patient_payer_profile_id', 'invoice_status', 'subtotal', 'discount_amount', 'tax_amount', 'total_amount', 'paid_amount', 'balance_amount', 'currency', 'issued_at', 'due_at', 'notes', 'created_by', 'updated_by'])]
class Invoice extends Model
{
    use HasFactory, SoftDeletes;
    protected function casts(): array { return ['payer_type' => PayerType::class, 'invoice_status' => InvoiceStatus::class, 'subtotal' => 'decimal:2', 'total_amount' => 'decimal:2', 'balance_amount' => 'decimal:2', 'issued_at' => 'datetime', 'due_at' => 'datetime']; }
    public function scopeForCurrentFacility(Builder $query): Builder { return $query->where('facility_id', currentFacility()?->id); }
    public function items(): HasMany { return $this->hasMany(InvoiceItem::class); }
    public function patientPayerProfile(): BelongsTo { return $this->belongsTo(PatientPayerProfile::class); }
    public function visit(): BelongsTo { return $this->belongsTo(Visit::class); }
}
