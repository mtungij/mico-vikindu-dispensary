<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCurrentFacility;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['facility_id','insurance_provider_id','insurance_scheme_id','payment_reference','payment_date','amount','currency','payment_method','bank_reference','period_from','period_to','status','received_by','notes','attachment_path'])]
class InsurancePayment extends Model
{
    use BelongsToCurrentFacility, HasFactory, SoftDeletes;
    protected function casts(): array { return ['payment_date'=>'date','amount'=>'decimal:2','period_from'=>'date','period_to'=>'date']; }
    public function allocations(): HasMany { return $this->hasMany(InsurancePaymentAllocation::class); }
}
