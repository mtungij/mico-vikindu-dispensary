<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['laboratory_sample_id', 'laboratory_order_item_id', 'status'])]
class LaboratorySampleItem extends Model
{
    use HasFactory;
    public function sample(): BelongsTo { return $this->belongsTo(LaboratorySample::class, 'laboratory_sample_id'); }
    public function orderItem(): BelongsTo { return $this->belongsTo(LaboratoryOrderItem::class, 'laboratory_order_item_id'); }
}
