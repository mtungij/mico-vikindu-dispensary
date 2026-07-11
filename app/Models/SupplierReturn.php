<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['facility_id', 'supplier_id', 'stock_location_id', 'return_number', 'status', 'reason', 'returned_at', 'created_by', 'approved_by', 'notes'])]
class SupplierReturn extends Model
{
    use HasFactory, SoftDeletes;
    protected function casts(): array { return ['returned_at' => 'datetime']; }
    public function scopeForCurrentFacility(Builder $query): Builder { return $query->where('facility_id', currentFacility()?->id); }
    public function items(): HasMany { return $this->hasMany(SupplierReturnItem::class); }
}
