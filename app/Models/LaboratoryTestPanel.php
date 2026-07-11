<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['facility_id', 'laboratory_test_id', 'child_laboratory_test_id', 'sort_order', 'is_required'])]
class LaboratoryTestPanel extends Model
{
    use HasFactory;
    protected function casts(): array { return ['is_required' => 'boolean']; }
    public function parent(): BelongsTo { return $this->belongsTo(LaboratoryTest::class, 'laboratory_test_id'); }
    public function child(): BelongsTo { return $this->belongsTo(LaboratoryTest::class, 'child_laboratory_test_id'); }
}
