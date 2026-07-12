<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCurrentFacility;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['facility_id','patient_id','visit_id','invoice_id','payment_id','receipt_number','receipt_date','amount','payment_method_snapshot','transaction_reference_snapshot','cashier_name_snapshot','status','original_receipt_id','reprint_count','created_by'])]
class Receipt extends Model
{
    use BelongsToCurrentFacility, HasFactory, SoftDeletes;
    protected function casts(): array { return ['receipt_date'=>'datetime','amount'=>'decimal:2']; }
    public function invoice(): BelongsTo { return $this->belongsTo(Invoice::class); }
    public function payment(): BelongsTo { return $this->belongsTo(Payment::class); }
    public function patient(): BelongsTo { return $this->belongsTo(Patient::class); }
}
