<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCurrentFacility;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['facility_id','insurance_claim_id','attachment_type','title','description','file_path','mime_type','file_size','uploaded_by','uploaded_at','is_required','verified_by','verified_at'])]
class InsuranceClaimAttachment extends Model
{
    use BelongsToCurrentFacility, HasFactory, SoftDeletes;
    protected function casts(): array { return ['uploaded_at'=>'datetime','verified_at'=>'datetime','is_required'=>'boolean']; }
}
