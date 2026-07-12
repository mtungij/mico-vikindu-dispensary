<?php

namespace App\Services;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;

class BillingAuditService
{
    public function record(string $event, ?Model $subject = null, array $values = []): void
    {
        ActivityLog::query()->create([
            'user_id' => auth()->id(),
            'event' => $event,
            'subject_type' => $subject ? $subject::class : null,
            'subject_id' => $subject?->getKey(),
            'old_values' => null,
            'new_values' => $values ?: null,
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
        ]);
    }
}
