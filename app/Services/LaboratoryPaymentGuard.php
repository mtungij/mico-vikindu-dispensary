<?php

namespace App\Services;

use App\Enums\ClinicalPaymentStatus;
use App\Models\ActivityLog;
use App\Models\LaboratoryOrder;
use App\Models\LaboratoryOrderItem;
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

    public function ensureItemProcessable(LaboratoryOrderItem $item, User $actor, string $action): void
    {
        abort_unless($item->order->facility_id === currentFacility()?->id && $actor->belongsToCurrentFacility(), 403);
        if ($item->isFinanciallyCleared()) {
            return;
        }
        $explicitSelection = $item->invoiceItem?->invoice->payments()
            ->where('status', 'confirmed')->where('metadata->allocation_mode', 'explicit')->exists();
        if ($explicitSelection || in_array($item->status, ['cancelled', 'not_performed', 'entered_in_error'], true)
            || ! $actor->can('laboratory.override-payment')) {
            throw ValidationException::withMessages(['payment' => 'Awaiting Payment: '.$item->test_name_snapshot]);
        }
        // Preserve the separately permissioned, audited override; never use order payment status here.
        ActivityLog::query()->create([
            'user_id' => $actor->id,
            'event' => 'laboratory_payment_override',
            'subject_type' => LaboratoryOrder::class,
            'subject_id' => $item->laboratory_order_id,
            'new_values' => ['laboratory_order_item_id' => $item->id, 'action' => $action],
        ]);
    }

    public function ensureProcessable(LaboratoryOrder $order, User $actor, string $action): void
    {
        abort_unless($order->facility_id === currentFacility()?->id && $actor->belongsToCurrentFacility(), 403);

        if (in_array($order->payment_status, self::ALLOWED, true)) {
            return;
        }

        if (! $actor->can('laboratory.override-payment')) {
            throw ValidationException::withMessages([
                'payment' => 'Malipo ya vipimo bado hayajakamilika.',
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
