<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['insurance_payment_id','insurance_claim_id','insurance_claim_item_id','allocated_amount','allocated_by','allocated_at','notes'])]
class InsurancePaymentAllocation extends Model
{
    use HasFactory;
    protected function casts(): array { return ['allocated_amount'=>'decimal:2','allocated_at'=>'datetime']; }
    public function payment(): BelongsTo { return $this->belongsTo(InsurancePayment::class, 'insurance_payment_id'); }
    public function claim(): BelongsTo { return $this->belongsTo(InsuranceClaim::class, 'insurance_claim_id'); }
}
