<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCurrentFacility;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['facility_id','claim_batch_id','insurance_provider_id','submission_method','submission_reference','submitted_at','submitted_by','status','acknowledgement_reference','acknowledgement_at','package_path','notes'])]
class InsuranceClaimSubmission extends Model
{
    use BelongsToCurrentFacility, HasFactory;
    protected function casts(): array { return ['submitted_at'=>'datetime','acknowledgement_at'=>'datetime']; }
}
