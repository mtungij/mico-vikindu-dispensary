<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCurrentFacility;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['facility_id','insurance_claim_id','invoice_item_id','service_id','medicine_id','laboratory_order_item_id','dental_procedure_id','observation_reference_id','rch_reference_type','rch_reference_id','item_type','service_code_snapshot','payer_service_code','description_snapshot','service_date','quantity','unit_price','gross_amount','patient_amount','claimed_amount','approved_amount','rejected_amount','paid_amount','diagnosis_code','procedure_code','medicine_code','coverage_status','authorization_number','status','rejection_reason_id','rejection_notes','metadata'])]
class InsuranceClaimItem extends Model
{
    use BelongsToCurrentFacility, HasFactory, SoftDeletes;
    protected function casts(): array { return ['service_date'=>'date','quantity'=>'decimal:3','unit_price'=>'decimal:2','gross_amount'=>'decimal:2','patient_amount'=>'decimal:2','claimed_amount'=>'decimal:2','approved_amount'=>'decimal:2','rejected_amount'=>'decimal:2','paid_amount'=>'decimal:2','metadata'=>'array']; }
    public function claim(): BelongsTo { return $this->belongsTo(InsuranceClaim::class, 'insurance_claim_id'); }
    public function invoiceItem(): BelongsTo { return $this->belongsTo(InvoiceItem::class); }
    public function service(): BelongsTo { return $this->belongsTo(Service::class); }
}
