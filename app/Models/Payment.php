<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCurrentFacility;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['facility_id','patient_id','visit_id','invoice_id','cashier_session_id','payment_number','payment_method_id','amount','currency','transaction_reference','payer_name','payer_phone','bank_name','card_last_four','payment_date','status','received_by','confirmed_by','confirmed_at','reversed_at','reversed_by','reversal_reason','notes','metadata'])]
class Payment extends Model
{
    use BelongsToCurrentFacility, HasFactory, SoftDeletes;
    protected function casts(): array { return ['amount'=>'decimal:2','payment_date'=>'datetime','confirmed_at'=>'datetime','reversed_at'=>'datetime','metadata'=>'array']; }
    public function invoice(): BelongsTo { return $this->belongsTo(Invoice::class); }
    public function method(): BelongsTo { return $this->belongsTo(PaymentMethod::class, 'payment_method_id'); }
    public function cashierSession(): BelongsTo { return $this->belongsTo(CashierSession::class); }
    public function receivedBy(): BelongsTo { return $this->belongsTo(User::class, 'received_by'); }
    public function allocations(): HasMany { return $this->hasMany(PaymentAllocation::class); }
    public function receipt(): HasOne { return $this->hasOne(Receipt::class); }
}
