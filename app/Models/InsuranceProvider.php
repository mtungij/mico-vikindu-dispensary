<?php

namespace App\Models;

use App\Enums\InsuranceProviderType;
use App\Models\Concerns\BelongsToCurrentFacility;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['facility_id', 'name', 'code', 'provider_type', 'registration_number', 'accreditation_number', 'contact_person', 'phone', 'email', 'address', 'website', 'claim_submission_method', 'payment_terms_days', 'default_currency', 'requires_pre_authorization', 'requires_referral', 'supports_dependants', 'supports_copayment', 'supports_partial_approval', 'claim_prefix', 'notes', 'is_active', 'created_by', 'updated_by'])]
class InsuranceProvider extends Model
{
    use BelongsToCurrentFacility, HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'provider_type' => InsuranceProviderType::class,
            'requires_pre_authorization' => 'boolean',
            'requires_referral' => 'boolean',
            'supports_dependants' => 'boolean',
            'supports_copayment' => 'boolean',
            'supports_partial_approval' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function schemes(): HasMany { return $this->hasMany(InsuranceScheme::class); }
    public function claims(): HasMany { return $this->hasMany(InsuranceClaim::class); }
}
