<?php

namespace App\Services;

use App\Enums\ClinicalOrderStatus;
use App\Enums\ClinicalPaymentStatus;
use App\Models\ActivityLog;
use App\Models\Invoice;
use App\Models\LaboratoryOrder;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LaboratoryPaymentReleaseService
{
    public function releaseForInvoice(Invoice $invoice, User $actor): void
    {
        DB::transaction(function () use ($invoice, $actor): void {
            $invoice = Invoice::query()->lockForUpdate()->findOrFail($invoice->id);

            if ((float) $invoice->balance_amount > 0 || $invoice->payment_status !== 'paid') {
                throw ValidationException::withMessages([
                    'invoice' => 'Laboratory orders require full payment before release.',
                ]);
            }

            $orders = LaboratoryOrder::query()
                ->where('facility_id', $invoice->facility_id)
                ->whereHas('items.invoiceItem', fn ($query) => $query->where('invoice_id', $invoice->id))
                ->where('payment_status', ClinicalPaymentStatus::Pending->value)
                ->lockForUpdate()
                ->get();

            foreach ($orders as $order) {
                $order->update([
                    'payment_status' => ClinicalPaymentStatus::Paid,
                    'status' => ClinicalOrderStatus::Ordered,
                    'updated_by' => $actor->id,
                ]);
                $order->items()->update(['status' => 'ready_for_collection']);

                $this->audit($actor, 'laboratory_payment_confirmed', $order, $invoice);
                $this->audit($actor, 'laboratory_released', $order, $invoice);
            }
        });
    }

    private function audit(User $actor, string $event, LaboratoryOrder $order, Invoice $invoice): void
    {
        ActivityLog::query()->create([
            'user_id' => $actor->id,
            'event' => $event,
            'subject_type' => $order::class,
            'subject_id' => $order->id,
            'new_values' => [
                'facility_id' => $order->facility_id,
                'visit_id' => $order->visit_id,
                'invoice_id' => $invoice->id,
                'laboratory_order_id' => $order->id,
                'payment_status' => ClinicalPaymentStatus::Paid->value,
                'status' => ClinicalOrderStatus::Ordered->value,
            ],
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
        ]);
    }
}
