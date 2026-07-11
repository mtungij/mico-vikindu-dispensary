<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['periodontal_assessment_id','tooth_number','site','pocket_depth_mm','recession_mm','bleeding_on_probing','suppuration','mobility_grade','furcation_grade','plaque_present','calculus_present'])]
class PeriodontalMeasurement extends Model
{
    use HasFactory;
    protected function casts(): array { return ['pocket_depth_mm'=>'decimal:2','recession_mm'=>'decimal:2','bleeding_on_probing'=>'boolean','suppuration'=>'boolean','plaque_present'=>'boolean','calculus_present'=>'boolean']; }
    public function assessment(): BelongsTo { return $this->belongsTo(PeriodontalAssessment::class, 'periodontal_assessment_id'); }
}
