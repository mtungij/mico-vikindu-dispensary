<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCurrentFacility;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['facility_id','payment_id','invoice_id','invoice_item_id','allocated_amount','allocation_type','allocated_by','allocated_at','reversed_at','reversal_reason'])]
class PaymentAllocation extends Model
{
    use BelongsToCurrentFacility, HasFactory;
    protected function casts(): array { return ['allocated_amount'=>'decimal:2','allocated_at'=>'datetime','reversed_at'=>'datetime']; }
    public function payment(): BelongsTo { return $this->belongsTo(Payment::class); }
    public function invoice(): BelongsTo { return $this->belongsTo(Invoice::class); }
}
