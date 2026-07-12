<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCurrentFacility;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['facility_id','insurance_claim_id','claim_batch_id','response_reference','response_date','response_status','approved_amount','rejected_amount','notes','response_file_path','received_by'])]
class InsuranceClaimResponse extends Model
{
    use BelongsToCurrentFacility, HasFactory;
    protected function casts(): array { return ['response_date'=>'date','approved_amount'=>'decimal:2','rejected_amount'=>'decimal:2']; }
    public function items(): HasMany { return $this->hasMany(InsuranceClaimItemResponse::class, 'claim_response_id'); }
}
