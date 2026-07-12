<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCurrentFacility;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['facility_id','insurance_provider_id','insurance_scheme_id','batch_number','batch_date','period_from','period_to','status','claims_count','total_claimed_amount','total_approved_amount','total_paid_amount','prepared_by','prepared_at','validated_by','validated_at','submitted_by','submitted_at','submission_reference','response_received_at','notes'])]
class InsuranceClaimBatch extends Model
{
    use BelongsToCurrentFacility, HasFactory, SoftDeletes;
    protected function casts(): array { return ['batch_date'=>'date','period_from'=>'date','period_to'=>'date','prepared_at'=>'datetime','validated_at'=>'datetime','submitted_at'=>'datetime','response_received_at'=>'datetime','total_claimed_amount'=>'decimal:2','total_approved_amount'=>'decimal:2','total_paid_amount'=>'decimal:2']; }
    public function claims(): HasMany { return $this->hasMany(InsuranceClaim::class, 'batch_id'); }
}
