<?php

namespace App\Models;

use App\Enums\CoverageStatus;
use App\Enums\PayerType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['patient_id', 'facility_id', 'payer_type', 'insurance_provider_id', 'corporate_account_id', 'membership_number', 'card_number', 'principal_member_name', 'relationship_to_principal', 'authorization_number', 'policy_number', 'scheme_name', 'valid_from', 'valid_to', 'coverage_status', 'is_primary', 'notes', 'created_by', 'updated_by'])]
class PatientPayerProfile extends Model
{
    use HasFactory, SoftDeletes;
    protected function casts(): array { return ['payer_type' => PayerType::class, 'valid_from' => 'date', 'valid_to' => 'date', 'coverage_status' => CoverageStatus::class, 'is_primary' => 'boolean']; }
    public function scopeForCurrentFacility(Builder $query): Builder { return $query->where('facility_id', currentFacility()?->id); }
    public function patient(): BelongsTo { return $this->belongsTo(Patient::class); }
    public function insuranceProvider(): BelongsTo { return $this->belongsTo(InsuranceProvider::class); }
    public function corporateAccount(): BelongsTo { return $this->belongsTo(CorporateAccount::class); }
}
