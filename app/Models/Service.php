<?php

namespace App\Models;

use App\Enums\PayerType;
use App\Enums\ServiceType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['facility_id', 'service_category_id', 'department_id', 'name', 'code', 'description', 'service_type', 'duration_minutes', 'requires_clinical_order', 'requires_payment', 'requires_appointment', 'allows_walk_in', 'taxable', 'queue_enabled', 'stock_related', 'is_active', 'sort_order', 'created_by', 'updated_by'])]
class Service extends Model
{
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'service_type' => ServiceType::class,
            'requires_clinical_order' => 'boolean',
            'requires_payment' => 'boolean',
            'requires_appointment' => 'boolean',
            'allows_walk_in' => 'boolean',
            'taxable' => 'boolean',
            'queue_enabled' => 'boolean',
            'stock_related' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function scopeForCurrentFacility(Builder $query): Builder
    {
        return $query->where('facility_id', currentFacility()?->id);
    }

    public function facility(): BelongsTo { return $this->belongsTo(Facility::class); }
    public function category(): BelongsTo { return $this->belongsTo(ServiceCategory::class, 'service_category_id'); }
    public function department(): BelongsTo { return $this->belongsTo(Department::class); }
    public function prices(): HasMany { return $this->hasMany(ServicePrice::class); }
    public function invoiceItems(): HasMany { return $this->hasMany(InvoiceItem::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function updater(): BelongsTo { return $this->belongsTo(User::class, 'updated_by'); }

    public function currentPriceFor(PayerType $payerType, ?int $insuranceProviderId = null, ?int $corporateAccountId = null): ?ServicePrice
    {
        return app(\App\Services\ServicePricingService::class)->getCurrentPrice($this, $payerType, $insuranceProviderId, $corporateAccountId);
    }
}
