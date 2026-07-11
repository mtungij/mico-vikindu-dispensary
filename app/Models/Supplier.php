<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['facility_id', 'name', 'code', 'supplier_type', 'contact_person', 'phone_primary', 'phone_secondary', 'email', 'physical_address', 'postal_address', 'region', 'district', 'tin_number', 'vrn_number', 'payment_terms_days', 'credit_limit', 'bank_details', 'notes', 'is_active', 'created_by', 'updated_by'])]
class Supplier extends Model
{
    use HasFactory, SoftDeletes;
    protected function casts(): array { return ['credit_limit' => 'decimal:2', 'is_active' => 'boolean']; }
    public function scopeForCurrentFacility(Builder $query): Builder { return $query->where('facility_id', currentFacility()?->id); }
}
