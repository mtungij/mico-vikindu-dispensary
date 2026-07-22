<?php

namespace App\Services;

use App\Enums\ClinicalPaymentStatus;
use App\Models\ActivityLog;
use App\Models\LaboratoryOrder;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class LaboratoryPaymentGuard
{
    /** @var array<int, ClinicalPaymentStatus> */
    private const ALLOWED = [
        ClinicalPaymentStatus::Paid,
        ClinicalPaymentStatus::Covered,
        ClinicalPaymentStatus::Waived,
        ClinicalPaymentStatus::NotRequired,
    ];

    public function ensureProcessable(LaboratoryOrder $order, User $actor, string $action): void
    {
        abort_unless($order->facility_id === currentFacility()?->id && $actor->belongsToCurrentFacility(), 403);

        if (in_array($order->payment_status, self::ALLOWED, true)) {
            return;
        }

        if (! $actor->can('laboratory.override-payment')) {
            throw ValidationException::withMessages([
                'payment' => 'Full payment or approved coverage is required before laboratory processing.',
            ]);
        }

        ActivityLog::query()->create([
            'user_id' => $actor->id,
            'event' => 'laboratory_payment_override',
            'subject_type' => $order::class,
            'subject_id' => $order->id,
            'new_values' => [
                'facility_id' => $order->facility_id,
                'visit_id' => $order->visit_id,
                'laboratory_order_id' => $order->id,
                'payment_status' => $order->payment_status->value,
                'action' => $action,
            ],
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
        ]);
    }
}
