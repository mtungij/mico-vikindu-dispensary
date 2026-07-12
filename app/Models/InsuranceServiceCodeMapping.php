<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCurrentFacility;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['facility_id','insurance_provider_id','insurance_scheme_id','service_id','payer_service_code','payer_service_name','procedure_code','effective_from','effective_to','is_active','notes'])]
class InsuranceServiceCodeMapping extends Model
{
    use BelongsToCurrentFacility, HasFactory, SoftDeletes;
    protected function casts(): array { return ['effective_from'=>'date','effective_to'=>'date','is_active'=>'boolean']; }
}
