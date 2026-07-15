<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VaccineBatchDetail extends Model
{
    protected $guarded = [];
    protected function casts(): array { return ['opened_at' => 'datetime', 'discard_at' => 'datetime']; }
    public function medicineBatch(): BelongsTo { return $this->belongsTo(MedicineBatch::class); }
    public function vaccine(): BelongsTo { return $this->belongsTo(Vaccine::class); }
}
