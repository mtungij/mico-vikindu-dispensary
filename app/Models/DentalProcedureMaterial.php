<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['dental_procedure_id','dental_material_id','quantity','unit_snapshot','batch_number','medicine_batch_id','unit_cost_snapshot','notes','created_by'])]
class DentalProcedureMaterial extends Model
{
    use HasFactory;
    protected function casts(): array { return ['quantity'=>'decimal:3','unit_cost_snapshot'=>'decimal:2']; }
    public function procedure(): BelongsTo { return $this->belongsTo(DentalProcedure::class, 'dental_procedure_id'); }
    public function material(): BelongsTo { return $this->belongsTo(DentalMaterial::class, 'dental_material_id'); }
}
