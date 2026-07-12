<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCurrentFacility;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['facility_id','name','code','type','requires_reference','requires_phone','requires_bank','is_cash','is_system','is_active','sort_order','created_by','updated_by'])]
class PaymentMethod extends Model
{
    use BelongsToCurrentFacility, HasFactory, SoftDeletes;
    protected function casts(): array { return ['requires_reference'=>'boolean','requires_phone'=>'boolean','requires_bank'=>'boolean','is_cash'=>'boolean','is_system'=>'boolean','is_active'=>'boolean']; }
    public function scopeForCurrentFacility(Builder $query): Builder { return $query->where(fn ($q) => $q->whereNull('facility_id')->orWhere('facility_id', currentFacility()?->id)); }
}
