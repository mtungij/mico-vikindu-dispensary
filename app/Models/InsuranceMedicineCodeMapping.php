<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCurrentFacility;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['facility_id','insurance_provider_id','insurance_scheme_id','medicine_id','payer_medicine_code','payer_medicine_name','dispensing_unit_snapshot','maximum_quantity','effective_from','effective_to','is_active','notes'])]
class InsuranceMedicineCodeMapping extends Model
{
    use BelongsToCurrentFacility, HasFactory, SoftDeletes;
    protected function casts(): array { return ['maximum_quantity'=>'decimal:3','effective_from'=>'date','effective_to'=>'date','is_active'=>'boolean']; }
}
