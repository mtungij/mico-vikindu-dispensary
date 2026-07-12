<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCurrentFacility;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['facility_id','insurance_claim_id','note_type','note','is_internal','created_by'])]
class InsuranceClaimNote extends Model
{
    use BelongsToCurrentFacility, HasFactory, SoftDeletes;
    protected function casts(): array { return ['is_internal'=>'boolean']; }
}
