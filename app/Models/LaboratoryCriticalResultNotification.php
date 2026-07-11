<?php

namespace App\Models;

use App\Enums\LaboratoryCriticalNotificationStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['facility_id', 'laboratory_result_id', 'laboratory_result_value_id', 'notified_to_user_id', 'notification_method', 'notified_by', 'notified_at', 'acknowledged_by', 'acknowledged_at', 'communication_notes', 'status'])]
class LaboratoryCriticalResultNotification extends Model
{
    use HasFactory;
    protected function casts(): array { return ['status' => LaboratoryCriticalNotificationStatus::class, 'notified_at' => 'datetime', 'acknowledged_at' => 'datetime']; }
    public function scopeForCurrentFacility(Builder $query): Builder { return $query->where('facility_id', currentFacility()?->id); }
    public function result(): BelongsTo { return $this->belongsTo(LaboratoryResult::class, 'laboratory_result_id'); }
}
