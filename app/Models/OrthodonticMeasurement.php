<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['orthodontic_case_id','measurement_type','value','unit','recorded_at','recorded_by','notes'])]
class OrthodonticMeasurement extends Model
{
    use HasFactory;
    protected function casts(): array { return ['recorded_at'=>'datetime']; }
    public function case(): BelongsTo { return $this->belongsTo(OrthodonticCase::class, 'orthodontic_case_id'); }
}
