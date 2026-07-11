<?php

namespace App\Models;

use App\Enums\InsuranceProviderType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['facility_id', 'name', 'code', 'provider_type', 'accreditation_number', 'contact_person', 'phone', 'email', 'address', 'claim_submission_method', 'payment_terms_days', 'is_active', 'created_by', 'updated_by'])]
class InsuranceProvider extends Model
{
    use HasFactory, SoftDeletes;
    protected function casts(): array { return ['provider_type' => InsuranceProviderType::class, 'is_active' => 'boolean']; }
    public function scopeForCurrentFacility(Builder $query): Builder { return $query->where('facility_id', currentFacility()?->id); }
}
