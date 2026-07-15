<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PregnancyDatingRecord extends Model
{
    use SoftDeletes;
    protected $guarded = [];
    protected function casts(): array { return ['reference_date' => 'date', 'calculated_edd' => 'date', 'is_primary' => 'boolean', 'recorded_at' => 'datetime']; }
    public function pregnancy(): BelongsTo { return $this->belongsTo(Pregnancy::class); }
}
