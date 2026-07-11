<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['facility_id', 'name', 'code', 'contact_person', 'phone', 'email', 'address', 'credit_limit', 'payment_terms_days', 'billing_cycle', 'is_active', 'created_by', 'updated_by'])]
class CorporateAccount extends Model
{
    use HasFactory, SoftDeletes;
    protected function casts(): array { return ['credit_limit' => 'decimal:2', 'is_active' => 'boolean']; }
    public function scopeForCurrentFacility(Builder $query): Builder { return $query->where('facility_id', currentFacility()?->id); }
}
