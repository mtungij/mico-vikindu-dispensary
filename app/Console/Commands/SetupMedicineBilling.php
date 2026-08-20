<?php

namespace App\Console\Commands;

use App\Enums\PayerType;
use App\Models\ActivityLog;
use App\Models\Medicine;
use App\Models\User;
use App\Services\MedicineBillingReadinessService;
use App\Services\MedicineBillingSetupService;
use Illuminate\Console\Command;
use Throwable;

class SetupMedicineBilling extends Command
{
    protected $signature = 'pharmacy:setup-medicine-billing
        {--apply : Apply only deterministic rows with an explicitly approved reference-price source}
        {--approve-reference-price : Explicitly approve positive default_dispensing_price values as cash prices}
        {--actor= : Authorized user ID recorded in audit logs; required with --apply}
        {--facility= : Limit to one facility ID; defaults to the current facility}
        {--ids=* : Limit to specific medicine IDs}';

    protected $description = 'Dry-run audit and safe setup of Medicine billing services and cash prices';

    public function handle(MedicineBillingSetupService $setup, MedicineBillingReadinessService $readiness): int
    {
        $apply = (bool) $this->option('apply');
        $approved = (bool) $this->option('approve-reference-price');
        $facilityId = (int) ($this->option('facility') ?: currentFacility()?->id);
        if ($facilityId < 1) {
            $this->error('A valid --facility is required.');

            return self::FAILURE;
        }

        $actor = null;
        if ($apply) {
            $actor = User::query()->find($this->option('actor'));
            if (! $actor || ! $actor->can('pharmacy.manage-medicines') || (! $actor->can('pharmacy.manage-prices') && ! $actor->can('services.manage-prices'))) {
                $this->error('--apply requires an authorized --actor with medicine and price management permissions.');

                return self::FAILURE;
            }
            if (! $actor->is_super_admin && $actor->staffProfile?->facility_id !== $facilityId) {
                $this->error('--actor does not belong to the selected facility.');

                return self::FAILURE;
            }
            if (! $approved) {
                $this->warn('No reference prices are approved. --apply will not create patient charges.');
            }
        }

        $ids = collect($this->option('ids'))->filter(fn ($id) => ctype_digit((string) $id))->map(fn ($id) => (int) $id)->unique();
        $query = Medicine::withTrashed()->with('service')->where('facility_id', $facilityId)->orderBy('id');
        if ($ids->isNotEmpty()) {
            $query->whereKey($ids);
        }

        $rows = [];
        $changed = 0;
        foreach ($query->get() as $medicine) {
            $classification = $setup->classifyForBulk($medicine, $approved);
            $current = $medicine->service
                ? $readiness->inspectForPayer($medicine, $facilityId, PayerType::Cash)['price']
                : null;
            $action = $classification['proposed_action'];
            if ($apply && str_starts_with($classification['risk'], 'safe_') && $classification['proposed_cash_price'] !== null) {
                try {
                    $oldServiceId = $medicine->service_id;
                    $result = $setup->setup($medicine, $classification['proposed_cash_price'], $actor);
                    ActivityLog::query()->create([
                        'user_id' => $actor->id,
                        'event' => 'medicine_billing_bulk_setup',
                        'subject_type' => Medicine::class,
                        'subject_id' => $medicine->id,
                        'old_values' => ['facility_id' => $facilityId, 'service_id' => $oldServiceId, 'cash_price' => $current?->amount],
                        'new_values' => ['facility_id' => $facilityId, 'service_id' => $result['service']->id, 'cash_price' => $result['price']?->amount, 'classification' => $classification['classification']],
                    ]);
                    $action = 'APPLIED: '.$action;
                    $changed++;
                    $medicine->refresh();
                } catch (Throwable $exception) {
                    $action = 'NOT APPLIED: '.$exception->getMessage();
                }
            }

            $rows[] = [
                $medicine->id,
                $medicine->code,
                $medicine->name,
                $medicine->facility_id,
                $medicine->service_id ?? 'NULL',
                $medicine->default_dispensing_price ?? 'NULL',
                $current?->amount ?? 'NULL',
                $classification['proposed_cash_price'] ?? '?',
                $classification['classification'],
                $classification['risk'],
                $classification['confidence'],
                $action,
            ];
        }

        $this->table(
            ['Medicine ID', 'Code', 'Name', 'Facility', 'Service ID', 'Reference price', 'Active cash price', 'Proposed cash', 'Classification', 'Risk', 'Confidence', 'Proposed action'],
            $rows,
        );
        $this->info(($apply ? 'Apply' : 'Dry run').' complete. Rows: '.count($rows)."; changed: {$changed}.");

        return self::SUCCESS;
    }
}
