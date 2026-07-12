<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['claim_response_id','insurance_claim_item_id','status','approved_amount','rejected_amount','rejection_reason_id','rejection_code','notes'])]
class InsuranceClaimItemResponse extends Model
{
    use HasFactory;
    protected function casts(): array { return ['approved_amount'=>'decimal:2','rejected_amount'=>'decimal:2']; }
}
