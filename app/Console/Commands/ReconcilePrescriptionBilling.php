<?php

namespace App\Console\Commands;

use App\Enums\PrescriptionStatus;
use App\Models\ActivityLog;
use App\Models\DispensingItem;
use App\Models\PatientQueue;
use App\Models\Prescription;
use App\Models\PrescriptionItem;
use App\Services\PrescriptionBillingService;
use App\Services\PrescriptionService;
use App\Services\VisitClosureService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class ReconcilePrescriptionBilling extends Command
{
    protected $signature = 'pharmacy:reconcile-prescription-billing {--apply : Apply only deterministic repairs}';

    protected $description = 'Audit legacy prescription billing, dispensing, stock, and Pharmacy queue consistency';

    public function handle(PrescriptionService $prescriptions, PrescriptionBillingService $billing, VisitClosureService $closure): int
    {
        $apply = (bool) $this->option('apply');
        $issues = 0;
        $fixed = 0;

        Prescription::query()->with(['encounter', 'items.invoiceItem', 'visit', 'dispensings'])->orderBy('id')->each(
            function (Prescription $prescription) use ($apply, $prescriptions, $billing, $closure, &$issues, &$fixed): void {
                $actor = $prescription->encounter?->completer ?? $prescription->encounter?->provider;
                $report = function (string $code, bool $ambiguous = false) use ($prescription, &$issues): void {
                    $issues++;
                    $this->line("Prescription {$prescription->id}: {$code}".($ambiguous ? ' [manual review]' : ''));
                };

                $nullQuantity = $prescription->items->contains(fn ($item) => $item->quantity === null || (float) $item->quantity <= 0);
                if ($nullQuantity) {
                    $report('null_or_invalid_quantity', true);
                }

                if ($prescription->encounter?->completed_at && $prescription->status === PrescriptionStatus::Draft) {
                    $report('completed_encounter_with_draft_prescription', $nullQuantity);
                    if ($apply && ! $nullQuantity && $actor && $this->attempt(fn () => $prescriptions->finalizePrescription($prescription, $actor))) {
                        $fixed++;
                    }
                }

                $missingLinks = $prescription->items->filter(fn ($item) => ! $item->invoice_item_id && ! $item->terminal_status);
                if ($missingLinks->isNotEmpty()) {
                    $report('missing_invoice_item_linkage', $nullQuantity);
                    if ($apply && ! $nullQuantity && $actor && $prescription->encounter?->completed_at
                        && $this->attempt(fn () => $billing->bill($prescription, $actor))) {
                        $fixed++;
                    }
                }

                $duplicateCharges = DB::table('invoice_items')
                    ->select(['invoice_id', 'reference_type', 'reference_id', 'service_id'])
                    ->where('reference_type', PrescriptionItem::class)
                    ->whereIn('reference_id', $prescription->items->modelKeys())
                    ->groupBy('invoice_id', 'reference_type', 'reference_id', 'service_id')
                    ->havingRaw('COUNT(*) > 1')
                    ->exists();
                if ($duplicateCharges) {
                    $report('duplicate_medicine_invoice_items', true);
                }

                if ($prescription->status === PrescriptionStatus::AwaitingPayment && $billing->isCleared($prescription)) {
                    $report('cleared_prescription_still_awaiting_payment');
                    if ($apply && $actor && $this->attempt(fn () => $billing->releasePrescription($prescription, $actor))) {
                        $fixed++;
                    }
                }

                $activeQueue = PatientQueue::query()->where('visit_id', $prescription->visit_id)
                    ->whereHas('department', fn ($query) => $query->where('code', 'PHA'))
                    ->whereIn('queue_status', ['waiting', 'called', 'serving'])->exists();
                if (in_array($prescription->status, [PrescriptionStatus::Dispensed, PrescriptionStatus::Cancelled], true) && $activeQueue) {
                    $report('terminal_prescription_with_active_pharmacy_queue');
                    if ($apply && $actor && $this->attempt(function () use ($prescription, $closure, $actor): void {
                        $closure->completeDepartmentQueues($prescription->visit, 'PHA', $actor);
                        $closure->evaluate($prescription->visit->refresh(), $actor);
                    })) {
                        $fixed++;
                    }
                }

                if ($prescription->status === PrescriptionStatus::Dispensed && $prescription->dispensings->isEmpty()) {
                    $report('dispensed_without_dispensing_record', true);
                }
            }
        );

        $stockMismatches = DB::table('dispensing_items as di')
            ->select(['di.id', 'di.dispensed_quantity'])
            ->leftJoin('stock_movements as sm', function ($join): void {
                $join->on('sm.reference_id', '=', 'di.id')->where('sm.reference_type', '=', DispensingItem::class);
            })
            ->where('di.status', '!=', 'reversed')
            ->groupBy('di.id', 'di.dispensed_quantity')
            ->havingRaw('COALESCE(SUM(sm.quantity), 0) <> di.dispensed_quantity')
            ->count();
        if ($stockMismatches > 0) {
            $issues += $stockMismatches;
            $this->warn("Stock movement inconsistencies: {$stockMismatches} [manual review]");
        }

        $this->info(($apply ? 'Applied' : 'Dry run')." — issues: {$issues}; deterministic repairs: {$fixed}");

        return self::SUCCESS;
    }

    private function attempt(callable $repair): bool
    {
        try {
            DB::transaction($repair);
            ActivityLog::query()->create(['user_id' => null, 'event' => 'prescription_billing_legacy_repaired', 'new_values' => ['command' => $this->getName()]]);

            return true;
        } catch (Throwable $exception) {
            $this->warn('Repair skipped: '.$exception->getMessage());

            return false;
        }
    }
}
