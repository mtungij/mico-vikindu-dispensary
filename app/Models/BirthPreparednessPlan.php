<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class BirthPreparednessPlan extends Model
{
    use SoftDeletes;
    protected $guarded = [];
    protected function casts(): array { return ['skilled_provider_identified' => 'boolean', 'funds_prepared' => 'boolean', 'blood_donor_identified' => 'boolean', 'danger_signs_counselling_done' => 'boolean', 'delivery_supplies_prepared' => 'boolean', 'prepared_at' => 'datetime']; }
    public function pregnancy(): BelongsTo { return $this->belongsTo(Pregnancy::class); }
    public function completionPercentage(): int { $fields = ['skilled_provider_identified','transport_plan','funds_prepared','blood_donor_identified','birth_companion','emergency_contact_name','danger_signs_counselling_done','delivery_supplies_prepared']; $done = collect($fields)->filter(fn($field) => filled($this->{$field}))->count(); return (int) round(($done / count($fields)) * 100); }
}
