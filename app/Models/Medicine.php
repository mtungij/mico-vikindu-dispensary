<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['facility_id', 'medicine_category_id', 'generic_medicine_id', 'dosage_form_id', 'default_route_id', 'purchase_unit_id', 'dispensing_unit_id', 'service_id', 'name', 'brand_name', 'code', 'barcode', 'strength', 'pack_size', 'purchase_to_dispensing_factor', 'manufacturer', 'country_of_origin', 'description', 'storage_instructions', 'reorder_level', 'maximum_stock_level', 'default_dispensing_price', 'prescription_required', 'controlled_drug', 'allow_substitution', 'track_batch', 'track_expiry', 'taxable', 'is_active', 'created_by', 'updated_by'])]
class Medicine extends Model
{
    use HasFactory, SoftDeletes;
    protected function casts(): array { return ['pack_size' => 'decimal:3', 'purchase_to_dispensing_factor' => 'decimal:3', 'reorder_level' => 'decimal:3', 'maximum_stock_level' => 'decimal:3', 'default_dispensing_price' => 'decimal:2', 'prescription_required' => 'boolean', 'controlled_drug' => 'boolean', 'allow_substitution' => 'boolean', 'track_batch' => 'boolean', 'track_expiry' => 'boolean', 'taxable' => 'boolean', 'is_active' => 'boolean']; }
    public function scopeForCurrentFacility(Builder $query): Builder { return $query->where('facility_id', currentFacility()?->id); }
    public function category(): BelongsTo { return $this->belongsTo(MedicineCategory::class, 'medicine_category_id'); }
    public function generic(): BelongsTo { return $this->belongsTo(GenericMedicine::class, 'generic_medicine_id'); }
    public function dosageForm(): BelongsTo { return $this->belongsTo(DosageForm::class); }
    public function route(): BelongsTo { return $this->belongsTo(MedicineRoute::class, 'default_route_id'); }
    public function purchaseUnit(): BelongsTo { return $this->belongsTo(MedicineUnit::class, 'purchase_unit_id'); }
    public function dispensingUnit(): BelongsTo { return $this->belongsTo(MedicineUnit::class, 'dispensing_unit_id'); }
    public function service(): BelongsTo { return $this->belongsTo(Service::class); }
    public function batches(): HasMany { return $this->hasMany(MedicineBatch::class); }
    public function movements(): HasMany { return $this->hasMany(StockMovement::class); }
    public function currentStock(?int $locationId = null): string { return (string) $this->batches()->when($locationId, fn ($q) => $q->where('stock_location_id', $locationId))->where('status', 'active')->sum('available_quantity'); }
}
