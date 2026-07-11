<?php

namespace App\Models;

use App\Enums\PayerType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['facility_id', 'service_id', 'payer_type', 'insurance_provider_id', 'corporate_account_id', 'amount', 'currency', 'effective_from', 'effective_to', 'is_active', 'notes', 'created_by', 'updated_by'])]
class ServicePrice extends Model
{
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return ['payer_type' => PayerType::class, 'amount' => 'decimal:2', 'effective_from' => 'date', 'effective_to' => 'date', 'is_active' => 'boolean'];
    }

    public function scopeForCurrentFacility(Builder $query): Builder { return $query->where('facility_id', currentFacility()?->id); }
    public function service(): BelongsTo { return $this->belongsTo(Service::class); }
    public function insuranceProvider(): BelongsTo { return $this->belongsTo(InsuranceProvider::class); }
    public function corporateAccount(): BelongsTo { return $this->belongsTo(CorporateAccount::class); }
}
