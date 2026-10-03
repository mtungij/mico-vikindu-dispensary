<?php

namespace App\Services;

use App\Enums\ClinicalOrderStatus;
use App\Enums\ClinicalPaymentStatus;
use App\Enums\VisitStatus;
use App\Models\ActivityLog;
use App\Models\Department;
use App\Models\Invoice;
use App\Models\LaboratoryOrder;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LaboratoryPaymentReleaseService
{
    public function __construct(private readonly WorkflowService $workflow) {}

    public function releaseForInvoice(Invoice $invoice, User $actor): void
    {
        DB::transaction(function () use ($invoice, $actor): void {
            $invoice = Invoice::query()->lockForUpdate()->findOrFail($invoice->id);

            $orders = LaboratoryOrder::query()
                ->where('facility_id', $invoice->facility_id)
                ->whereHas('items.invoiceItem', fn ($query) => $query->where('invoice_id', $invoice->id))
                ->whereNotIn('status', [ClinicalOrderStatus::Completed->value, ClinicalOrderStatus::Cancelled->value])
                ->lockForUpdate()
                ->get();

            foreach ($orders as $order) {
                $items = $order->items()->lockForUpdate()->get();
                $ready = $order->items()->financiallyCleared()->pluck('id');
                $newlyReady = $items->whereIn('id', $ready)->whereNull('sample_id')
                    ->whereIn('status', ['ordered', 'awaiting_payment', 'pending_collection']);
                $active = $items->whereNotIn('status', ['cancelled', 'not_performed', 'entered_in_error']);
                $allCleared = $active->isNotEmpty() && $active->every(fn ($item) => $ready->contains($item->id));
                $order->update([
                    'payment_status' => $allCleared
                        ? (in_array($order->payment_status, [ClinicalPaymentStatus::Covered, ClinicalPaymentStatus::Waived, ClinicalPaymentStatus::NotRequired], true)
                            ? $order->payment_status : ClinicalPaymentStatus::Paid)
                        : ClinicalPaymentStatus::Pending,
                    'status' => $ready->isNotEmpty() && $order->status === ClinicalOrderStatus::AwaitingPayment
                        ? ClinicalOrderStatus::Ordered : $order->status,
                    'updated_by' => $actor->id,
                ]);
                if ($newlyReady->isEmpty()) {
                    continue;
                }
                $order->items()->whereIn('id', $newlyReady->pluck('id'))->update(['status' => 'ready_for_collection']);
                $laboratory = Department::query()
                    ->where('facility_id', $order->facility_id)
                    ->where('code', 'LAB')
                    ->where('is_active', true)
                    ->where('queue_enabled', true)
                    ->first();
                if (! $laboratory) {
                    throw ValidationException::withMessages(['destination' => 'Laboratory queue is not configured.']);
                }
                $this->workflow->createQueue(
                    $order->visit,
                    $laboratory,
                    $actor,
                    VisitStatus::AwaitingLab,
                    'Laboratory payment cleared',
                    true,
                );

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
                'payment_status' => $order->payment_status->value,
                'status' => ClinicalOrderStatus::Ordered->value,
            ],
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
        ]);
    }
}
