<?php

namespace App\Services;

use App\Enums\PrescriptionStatus;
use App\Enums\VisitStatus;
use App\Models\Department;
use App\Models\Invoice;
use App\Models\Prescription;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PrescriptionBillingService
{
    public function __construct(
        private readonly InvoiceService $invoices,
        private readonly BillingChargeService $charges,
        private readonly InvoiceStatusService $statuses,
        private readonly WorkflowService $workflow,
    ) {}

    public function bill(Prescription $prescription, $actor): Prescription
    {
        return DB::transaction(function () use ($prescription, $actor): Prescription {
            $prescription = Prescription::query()
                ->with(['items.medicine.service', 'visit.invoice'])
                ->lockForUpdate()
                ->findOrFail($prescription->id);
            $invoice = $prescription->visit->invoice
                ?: $this->invoices->createVisitInvoice($prescription->visit, [], $actor);

            foreach ($prescription->items as $item) {
                if ((float) $item->quantity <= 0) {
                    throw ValidationException::withMessages(['prescription' => 'Every prescribed medicine must have a quantity greater than zero.']);
                }
                if (! $item->medicine || ! $item->medicine->is_active) {
                    throw ValidationException::withMessages(['prescription' => "{$item->medication_name} is not linked to an active medicine."]);
                }
                if (! $item->medicine->service) {
                    $item->update([
                        'remaining_quantity' => max(0, (float) $item->quantity - (float) $item->dispensed_quantity),
                    ]);

                    continue;
                }
                if (! $item->medicine->service->is_active) {
                    throw ValidationException::withMessages(['prescription' => "{$item->medication_name} is linked to an inactive billing service."]);
                }

                $invoiceItem = $this->charges->addServiceCharge(
                    $invoice,
                    $item->medicine->service,
                    $actor,
                    $item,
                    (float) $item->quantity,
                    ['source' => 'prescription', 'prescription_id' => $prescription->id],
                );
                $item->update([
                    'service_id' => $item->medicine->service_id,
                    'invoice_item_id' => $invoiceItem->id,
                    'remaining_quantity' => max(0, (float) $item->quantity - (float) $item->dispensed_quantity),
                    'unit_price_snapshot' => $invoiceItem->unit_price,
                    'patient_amount' => $invoiceItem->patient_amount,
                    'insurance_amount' => $invoiceItem->insurance_amount,
                    'payer_amount' => $invoiceItem->payer_amount,
                ]);
            }

            $invoice = $this->statuses->recalculate($invoice);
            $prescription->update([
                'status' => (float) $invoice->balance_amount > 0
                    ? PrescriptionStatus::AwaitingPayment
                    : PrescriptionStatus::Prescribed,
                'updated_by' => $actor->id,
            ]);

            return $prescription->refresh();
        });
    }

    public function releasePaidInvoice(Invoice $invoice, $actor): void
    {
        DB::transaction(function () use ($invoice, $actor): void {
            $invoice = Invoice::query()->lockForUpdate()->findOrFail($invoice->id);
            $invoice = $this->statuses->recalculate($invoice);
            if ((float) $invoice->balance_amount > 0) {
                return;
            }

            $prescriptions = Prescription::query()
                ->where('visit_id', $invoice->visit_id)
                ->where('status', PrescriptionStatus::AwaitingPayment->value)
                ->whereHas('items.invoiceItem', fn ($query) => $query->where('invoice_id', $invoice->id))
                ->lockForUpdate()
                ->get();
            if ($prescriptions->isEmpty()) {
                return;
            }

            $prescriptions->each(fn (Prescription $prescription) => $prescription->update([
                'status' => PrescriptionStatus::Prescribed,
                'updated_by' => $actor->id,
            ]));
            $pharmacy = Department::query()
                ->where('facility_id', $invoice->facility_id)
                ->where('code', 'PHA')
                ->where('is_active', true)
                ->where('queue_enabled', true)
                ->first();
            if (! $pharmacy) {
                throw ValidationException::withMessages(['destination' => 'Pharmacy queue is not configured.']);
            }
            $this->workflow->createQueue(
                $invoice->visit,
                $pharmacy,
                $actor,
                VisitStatus::AwaitingPharmacy,
                'Medicine payment cleared',
                true,
            );
        });
    }
}
