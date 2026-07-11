<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['dental_procedure_id','patient_expectations','baseline_shade','final_shade','product_or_material','sessions','sensitivity','aftercare_instructions'])]
class DentalCosmeticProcedureDetail extends Model
{
    use HasFactory;
    public function procedure(): BelongsTo { return $this->belongsTo(DentalProcedure::class, 'dental_procedure_id'); }
}
