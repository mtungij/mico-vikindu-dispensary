<?php

namespace App\Console\Commands;

use App\Enums\PrescriptionStatus;
use App\Models\ActivityLog;
use App\Models\DispensingItem;
use App\Models\InvoiceItem;
use App\Models\PatientQueue;
use App\Models\Prescription;
use App\Models\PrescriptionItem;
use App\Services\PrescriptionBillingService;
use App\Services\ServicePricingService;
use App\Services\VisitClosureService;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

class ReconcilePrescriptionBilling extends Command
{
    protected $signature = 'pharmacy:reconcile-prescription-billing
        {--apply : Apply only unambiguous existing-charge links and workflow-state repairs}
        {--ids=* : Limit the report to specific prescription IDs}
        {--recent=0 : Report the newest N prescriptions, including healthy records}';

    protected $description = 'Audit legacy prescription billing, payment, dispensing, stock, and Pharmacy queue consistency';

    private int $issues = 0;

    private int $fixed = 0;

    public function handle(
        PrescriptionBillingService $billing,
        VisitClosureService $closure,
        ServicePricingService $pricing,
    ): int {
        $apply = (bool) $this->option('apply');
        $ids = collect($this->option('ids'))->filter(fn ($id) => ctype_digit((string) $id))->map(fn ($id) => (int) $id)->unique()->values();
        $recent = max(0, (int) $this->option('recent'));

        if ($apply) {
            $this->warn('APPLY mode never reconstructs quantities, creates retroactive charges, reopens terminal visits, or changes stock.');
        }

        $query = Prescription::query()->with($this->reportRelations());
        if ($ids->isNotEmpty()) {
            $query->whereKey($ids);
        }
        if ($recent > 0) {
            $query->latest('id')->limit($recent);
        } else {
            $query->orderBy('id');
        }

        $prescriptions = $query->get();
        foreach ($prescriptions as $prescription) {
            $this->inspectPrescription($prescription, $billing, $closure, $pricing, $apply, $ids->isNotEmpty() || $recent > 0);
        }

        if ($ids->isNotEmpty()) {
            $missing = $ids->diff($prescriptions->modelKeys());
            foreach ($missing as $id) {
                $this->warn("Prescription {$id}: not_found");
            }
        }

        $this->inspectStockMismatches($ids);
        $this->info(($apply ? 'Applied' : 'Dry run')." — issues: {$this->issues}; deterministic repairs: {$this->fixed}");

        return self::SUCCESS;
    }

    /** @return array<int, string> */
    private function reportRelations(): array
    {
        return [
            'patient',
            'visit.invoice.items',
            'visit.invoice.payments.allocations',
            'encounter.provider',
            'encounter.completer',
            'items.medicine.service.prices',
            'items.invoiceItem',
            'dispensings.items.allocations.batch',
        ];
    }

    private function inspectPrescription(
        Prescription $prescription,
        PrescriptionBillingService $billing,
        VisitClosureService $closure,
        ServicePricingService $pricing,
        bool $apply,
        bool $alwaysShow,
    ): void {
        $before = $this->issues;
        $invoice = $prescription->visit?->invoice;
        $encounter = $prescription->encounter;
        $provider = $encounter?->provider;

        $this->newLine();
        $this->line(sprintf(
            'Prescription %d / %s | facility=%s | patient=%s (%s, id=%s) | visit=%s (id=%s)',
            $prescription->id,
            $prescription->prescription_number,
            $prescription->facility_id,
            $prescription->patient?->fullName() ?: 'unknown',
            $prescription->patient?->patient_number ?: 'unknown',
            $prescription->patient_id,
            $prescription->visit?->visit_number ?: 'unknown',
            $prescription->visit_id,
        ));
        $this->line(sprintf(
            '  encounter=%s status=%s completed_at=%s outcome=%s provider=%s (id=%s) prescribed_by=%s | prescription_status=%s | visit_status=%s',
            $encounter?->id ?? 'none',
            $this->value($encounter?->status),
            $encounter?->completed_at?->toDateTimeString() ?? 'NULL',
            $this->value($encounter?->outcome),
            $provider?->name ?? 'unknown',
            $provider?->id ?? $prescription->prescribed_by,
            $prescription->prescribed_by,
            $this->value($prescription->status),
            $this->value($prescription->visit?->visit_status),
        ));
        $this->reportInvoice($invoice);

        foreach ($prescription->items as $item) {
            $this->inspectItem($prescription, $item, $invoice, $pricing, $apply);
        }

        $this->reportDispensing($prescription);
        $this->inspectPrescriptionState($prescription, $billing, $closure, $apply);

        if ($alwaysShow && $this->issues === $before) {
            $this->info('  workflow_integrity=ok');
        }
    }

    private function inspectItem(Prescription $prescription, PrescriptionItem $item, $invoice, ServicePricingService $pricing, bool $apply): void
    {
        $medicine = $item->medicine;
        $service = $medicine?->service ?? $item->service;
        $price = $invoice && $service
            ? $pricing->getCurrentPrice(
                $service,
                $invoice->payer_type,
                $invoice->insurance_provider_id ?? $invoice->patientPayerProfile?->insurance_provider_id,
                $invoice->corporate_account_id ?? $invoice->patientPayerProfile?->corporate_account_id,
            )
            : null;

        $this->line(sprintf(
            '  Item %d | medicine=%s (%s, id=%s) | dose=%s frequency=%s duration=%s %s route=%s instructions=%s',
            $item->id,
            $medicine?->name ?? $item->medication_name,
            $medicine?->code ?? 'unknown',
            $item->medicine_id ?? 'NULL',
            $item->dose ?: 'NULL',
            $item->frequency ?: 'NULL',
            $item->duration_value ?? 'NULL',
            $item->duration_unit ?: 'NULL',
            $item->route ?: 'NULL',
            $item->instructions ?: 'NULL',
        ));
        $this->line(sprintf(
            '    quantity=%s dispensed=%s remaining=%s status=%s terminal=%s invoice_item_id=%s unit_price=%s patient=%s insurance=%s',
            $item->quantity ?? 'NULL',
            $item->dispensed_quantity ?? 'NULL',
            $item->remaining_quantity ?? 'NULL',
            $item->status,
            $item->terminal_status ?? 'NULL',
            $item->invoice_item_id ?? 'NULL',
            $item->unit_price_snapshot ?? 'NULL',
            $item->patient_amount ?? 'NULL',
            $item->insurance_amount ?? 'NULL',
        ));
        $this->line(sprintf(
            '    billing_service=%s active=%s requires_payment=%s price_id=%s current_price=%s',
            $service?->id ?? 'NULL',
            $service ? ($service->is_active ? 'yes' : 'no') : 'no',
            $service ? ($service->requires_payment ? 'yes' : 'no') : 'unknown',
            $price?->id ?? 'NULL',
            $price?->amount ?? 'NULL',
        ));

        $hasDispensing = $this->itemDispensingItems($prescription, $item)->isNotEmpty();
        if ($item->quantity === null || (float) $item->quantity <= 0) {
            $proposal = $this->quantityProposal($item);
            $code = $proposal ? 'safe_reconstructable_quantity' : 'ambiguous_quantity';
            $this->issue($prescription, $item, 'invalid_quantity', true, [
                'classification' => $code,
                'proposed_quantity' => $proposal ?? 'none',
                'financial_impact' => 'unknown',
                'stock_impact' => $hasDispensing ? 'existing dispensing history; do not alter automatically' : 'none recorded',
            ]);
        }

        if ($item->invoice_item_id || $item->terminal_status) {
            return;
        }

        $candidates = $this->legacyChargeCandidates($prescription, $item, $invoice);
        if ($candidates->count() === 1) {
            $candidate = $candidates->first();
            $safeLink = $this->safeToLink($prescription, $item, $candidate);
            $allocated = (float) $invoice->payments
                ->flatMap->allocations
                ->where('invoice_item_id', $candidate->id)
                ->whereNull('reversed_at')
                ->sum('allocated_amount');
            $alreadyPaid = $allocated > 0 || in_array($invoice->payment_status, ['paid', 'covered', 'overpaid'], true);
            $this->issue($prescription, $item, 'existing_charge_missing_link', ! $safeLink, [
                'invoice_item' => $candidate->id,
                'candidate_quantity' => $candidate->quantity,
                'candidate_total' => $candidate->total_amount,
                'allocated' => $allocated,
                'classification' => ! $safeLink ? 'candidate_already_linked_elsewhere' : ($alreadyPaid ? 'already_paid_unlinked' : 'deterministic_link_only'),
            ]);
            if ($apply && $safeLink) {
                $this->attempt(function () use ($item, $candidate): void {
                    $item->update([
                        'invoice_item_id' => $candidate->id,
                        'service_id' => $item->service_id ?: $candidate->service_id,
                        'unit_price_snapshot' => $item->unit_price_snapshot ?? $candidate->unit_price,
                        'patient_amount' => $item->patient_amount ?? $candidate->patient_amount,
                        'insurance_amount' => $item->insurance_amount ?? $candidate->insurance_amount,
                        'payer_amount' => $item->payer_amount ?? $candidate->payer_amount,
                    ]);
                }, $prescription, $item, 'linked_existing_legacy_charge');
            }

            return;
        }

        if ($candidates->count() > 1) {
            $this->issue($prescription, $item, 'existing_charge_missing_link', true, [
                'candidate_invoice_items' => $candidates->pluck('id')->implode(','),
                'classification' => 'multiple_candidates',
            ]);

            return;
        }

        $terminalVisit = in_array($this->value($prescription->visit?->visit_status), ['completed', 'referred', 'cancelled', 'discharged'], true);
        $financiallyClosed = $invoice && in_array($invoice->payment_status, ['paid', 'covered', 'overpaid'], true);
        $classification = match (true) {
            $hasDispensing => 'already_dispensed_unlinked',
            $terminalVisit || $financiallyClosed => 'legacy_unbilled_closed_visit',
            default => 'missing_charge',
        };
        $this->issue($prescription, $item, $classification, true, [
            'invoice' => $invoice?->id ?? 'none',
            'visit_status' => $this->value($prescription->visit?->visit_status),
            'payment_status' => $invoice?->payment_status ?? 'none',
            'action' => 'no automatic charge creation',
        ]);
    }

    private function reportInvoice($invoice): void
    {
        if (! $invoice) {
            $this->line('  invoice=none');

            return;
        }
        $confirmed = $invoice->payments->where('status', 'confirmed');
        $allocated = $confirmed->flatMap->allocations->whereNull('reversed_at')->sum('allocated_amount');
        $this->line(sprintf(
            '  invoice=%s (id=%d) status=%s payment_status=%s total=%s patient=%s insurance=%s paid=%s balance=%s confirmed_payments=%d allocated=%s',
            $invoice->invoice_number,
            $invoice->id,
            $this->value($invoice->invoice_status),
            $invoice->payment_status,
            $invoice->total_amount,
            $invoice->patient_amount,
            $invoice->insurance_amount,
            $invoice->paid_amount,
            $invoice->balance_amount,
            $confirmed->count(),
            $allocated,
        ));
        foreach ($confirmed as $payment) {
            $this->line(sprintf(
                '    payment=%s (id=%d) amount=%s confirmed_at=%s allocations=%s',
                $payment->payment_number,
                $payment->id,
                $payment->amount,
                $payment->confirmed_at?->toDateTimeString() ?? 'NULL',
                $payment->allocations->whereNull('reversed_at')->map(fn ($allocation) => ($allocation->invoice_item_id ?? 'invoice').':'.$allocation->allocated_amount)->implode(','),
            ));
        }
    }

    private function reportDispensing(Prescription $prescription): void
    {
        $items = $prescription->dispensings->flatMap->items;
        $allocations = $items->flatMap->allocations;
        $movementCount = DB::table('stock_movements')
            ->where('reference_type', DispensingItem::class)
            ->whereIn('reference_id', $items->pluck('id'))
            ->count();
        $this->line(sprintf(
            '  dispensing_records=%d dispensing_items=%d batch_allocations=%d stock_movements=%d',
            $prescription->dispensings->count(),
            $items->count(),
            $allocations->count(),
            $movementCount,
        ));
        foreach ($items as $item) {
            $this->line(sprintf(
                '    dispensing_item=%d prescription_item=%s medicine=%s prescribed_quantity=%s dispensed_quantity=%s status=%s batches=%s',
                $item->id,
                $item->prescription_item_id ?? 'NULL',
                $item->medicine_id,
                $item->prescribed_quantity ?? 'NULL',
                $item->dispensed_quantity,
                $item->status,
                $item->allocations->map(fn ($allocation) => ($allocation->medicine_batch_id ?? 'NULL').':'.$allocation->quantity)->implode(','),
            ));
        }
    }

    private function inspectPrescriptionState(Prescription $prescription, PrescriptionBillingService $billing, VisitClosureService $closure, bool $apply): void
    {
        $duplicateCharges = DB::table('invoice_items')
            ->select(['invoice_id', 'reference_type', 'reference_id', 'service_id'])
            ->where('reference_type', PrescriptionItem::class)
            ->whereIn('reference_id', $prescription->items->modelKeys())
            ->groupBy('invoice_id', 'reference_type', 'reference_id', 'service_id')
            ->havingRaw('COUNT(*) > 1')
            ->exists();
        if ($duplicateCharges) {
            $this->issue($prescription, null, 'duplicate_medicine_invoice_items', true);
        }

        if ($prescription->status === PrescriptionStatus::AwaitingPayment && $billing->isCleared($prescription)) {
            $this->issue($prescription, null, 'cleared_prescription_still_awaiting_payment', false);
            if ($apply && ! $this->terminalVisit($prescription)) {
                $actor = $prescription->encounter?->completer ?? $prescription->encounter?->provider;
                if ($actor) {
                    $this->attempt(fn () => $billing->releasePrescription($prescription, $actor), $prescription, null, 'released_cleared_prescription');
                }
            }
        }

        $activeQueue = PatientQueue::query()->where('visit_id', $prescription->visit_id)
            ->whereHas('department', fn ($query) => $query->where('code', 'PHA'))
            ->whereIn('queue_status', ['waiting', 'called', 'serving'])->exists();
        if (in_array($prescription->status, [PrescriptionStatus::Dispensed, PrescriptionStatus::Cancelled], true) && $activeQueue) {
            $this->issue($prescription, null, 'terminal_prescription_with_active_pharmacy_queue', false);
            $actor = $prescription->encounter?->completer ?? $prescription->encounter?->provider;
            if ($apply && $actor) {
                $this->attempt(function () use ($prescription, $closure, $actor): void {
                    $closure->completeDepartmentQueues($prescription->visit, 'PHA', $actor);
                    $closure->evaluate($prescription->visit->refresh(), $actor);
                }, $prescription, null, 'closed_terminal_pharmacy_queue');
            }
        }

        if ($prescription->status === PrescriptionStatus::Dispensed && $prescription->dispensings->isEmpty()) {
            $this->issue($prescription, null, 'dispensed_without_dispensing_record', true);
        }
    }

    private function legacyChargeCandidates(Prescription $prescription, PrescriptionItem $item, $invoice): Collection
    {
        if (! $invoice) {
            return collect();
        }
        $serviceId = $item->service_id ?: $item->medicine?->service_id;

        return $invoice->items
            ->whereNotIn('status', ['cancelled', 'reversed'])
            ->filter(function (InvoiceItem $candidate) use ($prescription, $item, $serviceId): bool {
                if ($candidate->reference_type === PrescriptionItem::class && (int) $candidate->reference_id === $item->id) {
                    return true;
                }
                if ($candidate->reference_type || $candidate->reference_id || ! $serviceId || (int) $candidate->service_id !== (int) $serviceId) {
                    return false;
                }
                $metadata = $candidate->metadata ?? [];
                $looksMedicinal = in_array($candidate->item_type, ['medicine', 'pharmacy'], true)
                    || in_array($metadata['source'] ?? null, ['prescription', 'pharmacy', 'medicine'], true)
                    || (int) ($metadata['medicine_id'] ?? 0) === (int) $item->medicine_id;
                $quantityMatches = $item->quantity === null || abs((float) $candidate->quantity - (float) $item->quantity) <= 0.005;

                if ($item->quantity === null || (float) $item->quantity <= 0) {
                    return $looksMedicinal
                        && (int) ($metadata['prescription_id'] ?? 0) === $prescription->id
                        && ((int) ($metadata['prescription_item_id'] ?? 0) === $item->id
                            || (int) ($metadata['medicine_id'] ?? 0) === (int) $item->medicine_id);
                }

                return $looksMedicinal && $quantityMatches;
            })
            ->values();
    }

    private function safeToLink(Prescription $prescription, PrescriptionItem $item, InvoiceItem $candidate): bool
    {
        return $candidate->invoice_id === $prescription->visit?->invoice?->id
            && ! PrescriptionItem::query()->where('invoice_item_id', $candidate->id)->whereKeyNot($item->id)->exists();
    }

    private function quantityProposal(PrescriptionItem $item): ?float
    {
        $dose = trim((string) $item->dose);
        $frequency = strtoupper(trim((string) $item->frequency));
        $duration = (int) $item->duration_value;
        if ($duration < 1 || str_contains($frequency, 'PRN')) {
            return null;
        }
        if (! preg_match('/^(\d+(?:\.\d+)?)\s*(tab(?:let)?s?|cap(?:sule)?s?)$/i', $dose, $match)) {
            return null;
        }
        $perDay = ['OD' => 1, 'DAILY' => 1, 'BD' => 2, 'BID' => 2, 'TDS' => 3, 'TID' => 3, 'QID' => 4][$frequency] ?? null;
        if (! $perDay) {
            return null;
        }
        $days = $duration * match (strtolower((string) $item->duration_unit)) {
            'day', 'days' => 1,
            'week', 'weeks' => 7,
            'month', 'months' => 30,
            default => 0,
        };

        return $days > 0 ? (float) $match[1] * $perDay * $days : null;
    }

    private function itemDispensingItems(Prescription $prescription, PrescriptionItem $item): Collection
    {
        return $prescription->dispensings->flatMap->items->where('prescription_item_id', $item->id);
    }

    private function terminalVisit(Prescription $prescription): bool
    {
        return in_array($this->value($prescription->visit?->visit_status), ['completed', 'referred', 'cancelled', 'discharged'], true);
    }

    private function issue(Prescription $prescription, ?PrescriptionItem $item, string $code, bool $manual, array $context = []): void
    {
        $this->issues++;
        $identity = "Prescription {$prescription->id}".($item ? " / Item {$item->id}" : '').' / '.($prescription->visit?->visit_number ?? 'no-visit');
        $suffix = $context === [] ? '' : ' | '.collect($context)->map(fn ($value, $key) => "{$key}={$value}")->implode(' ');
        $this->line("  {$identity}: {$code}{$suffix} ".($manual ? '[manual review]' : '[deterministic]'));
    }

    private function inspectStockMismatches(Collection $ids): void
    {
        $query = DB::table('dispensing_items as di')
            ->join('dispensings as d', 'd.id', '=', 'di.dispensing_id')
            ->select(['di.id', 'di.dispensed_quantity'])
            ->leftJoin('stock_movements as sm', function ($join): void {
                $join->on('sm.reference_id', '=', 'di.id')->where('sm.reference_type', '=', DispensingItem::class);
            })
            ->where('di.status', '!=', 'reversed')
            ->when($ids->isNotEmpty(), fn ($query) => $query->whereIn('d.prescription_id', $ids))
            ->groupBy('di.id', 'di.dispensed_quantity')
            ->havingRaw('COALESCE(SUM(sm.quantity), 0) <> di.dispensed_quantity');
        $count = DB::query()->fromSub($query, 'stock_mismatches')->count();
        if ($count > 0) {
            $this->issues += $count;
            $this->warn("Stock movement inconsistencies: {$count} [manual review]");
        }
    }

    private function attempt(callable $repair, Prescription $prescription, ?PrescriptionItem $item, string $action): void
    {
        try {
            DB::transaction($repair);
            ActivityLog::query()->create([
                'user_id' => null,
                'event' => 'prescription_billing_legacy_repaired',
                'subject_type' => $item ? PrescriptionItem::class : Prescription::class,
                'subject_id' => $item?->id ?? $prescription->id,
                'new_values' => ['command' => $this->getName(), 'action' => $action, 'prescription_id' => $prescription->id],
            ]);
            $this->fixed++;
        } catch (Throwable $exception) {
            $this->warn('Repair skipped: '.$exception->getMessage());
        }
    }

    private function value(mixed $value): string
    {
        return (string) ($value instanceof \BackedEnum ? $value->value : ($value ?? 'NULL'));
    }
}
