<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['dental_procedure_id','cavity_class','preparation_type','liner_used','base_used','restorative_material','shade','matrix_used','finishing_polishing_done','occlusion_checked','notes'])]
class DentalRestorativeDetail extends Model
{
    use HasFactory;
    protected function casts(): array { return ['finishing_polishing_done'=>'boolean','occlusion_checked'=>'boolean']; }
    public function procedure(): BelongsTo { return $this->belongsTo(DentalProcedure::class, 'dental_procedure_id'); }
}
