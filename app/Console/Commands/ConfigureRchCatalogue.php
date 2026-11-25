<?php

namespace App\Console\Commands;

use App\Enums\PayerType;
use App\Enums\RchService;
use App\Enums\ServiceType;
use App\Models\ActivityLog;
use App\Models\Department;
use App\Models\Facility;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\ServicePrice;
use App\Models\User;
use App\Services\ServicePricingService;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class ConfigureRchCatalogue extends Command
{
    protected $signature = 'rch:configure-catalogue
        {--facility= : Facility ID to audit or update}
        {--apply : Apply the reviewed plan to this facility}
        {--actor= : Authorized service administrator ID for the audit log}';

    protected $description = 'Audit and configure the five Reception RCH services for one facility';

    public function handle(ServicePricingService $pricing): int
    {
        $facilityId = filter_var($this->option('facility'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $department = $facilityId ? Department::query()->where('facility_id', $facilityId)
            ->where('code', 'RCH')->where('is_active', true)->where('can_receive_patients', true)->first() : null;

        if (! $department) {
            $this->error('Use --facility with an existing, active RCH destination.');

            return self::FAILURE;
        }

        $actor = null;
        if ($this->option('apply')) {
            $actor = User::query()->find($this->option('actor'));
            if (! $actor?->isActive() || (! $actor->is_super_admin && ($actor->staffProfile?->facility_id !== $facilityId
                || ! $actor->hasAllPermissions(['services.create', 'services.update', 'services.activate', 'services.manage-prices'])))) {
                $this->error('--apply requires --actor with service catalogue and price management permissions for this facility.');

                return self::FAILURE;
            }
        }

        return DB::transaction(function () use ($department, $actor, $pricing): int {
            if ($actor) {
                Facility::query()->lockForUpdate()->findOrFail($department->facility_id);
            }

            $plans = [];
            $rows = [];
            $blockers = [];
            foreach (RchService::cases() as $definition) {
                $services = Service::withTrashed()->where('facility_id', $department->facility_id);
                $matches = (clone $services)->whereRaw('UPPER(code) = ?', [$definition->value])->get();
                if ($matches->count() > 1) {
                    $blockers[] = "Multiple records match {$definition->value}. Review their IDs before applying.";

                    continue;
                }

                $service = $matches->first();
                $names = match ($definition) {
                    RchService::ChildMonitoring => ['child growth monitoring', 'child monitoring'],
                    RchService::FamilyPlanning => ['family planning consultation', 'family planning'],
                    default => [strtolower($definition->catalogueName())],
                };
                if (! $service && ($alias = $services->whereIn(DB::raw('LOWER(name)'), $names)->first())) {
                    $blockers[] = "Review service #{$alias->id} ({$alias->code}) for {$definition->value}. No duplicate will be created.";

                    continue;
                }

                if ($service && ! in_array($service->service_type, [$definition->serviceType(), ServiceType::Consultation, ServiceType::Other], true)) {
                    $blockers[] = "Service #{$service->id} ({$definition->value}) has type {$service->service_type->value}. Review its mapping before applying.";

                    continue;
                }

                if ($service && $definition === RchService::FamilyPlanning) {
                    $contractTariffs = $this->prices($service)->whereIn('payer_type', [PayerType::Insurance->value, PayerType::Corporate->value])
                        ->where('amount', '>', 0)->get();
                    foreach ($contractTariffs as $tariff) {
                        $payer = $tariff->insurance_provider_id ?? $tariff->corporate_account_id ?? 'unassigned';
                        $blockers[] = "Family Planning tariff #{$tariff->id} for {$tariff->payer_type->value} payer {$payer}: {$tariff->currency} {$tariff->amount}. Resolve with the contract owner before applying.";
                    }
                }

                $price = $service ? $pricing->getCurrentPrice($service, PayerType::Cash) : null;
                $standardize = in_array($definition, [RchService::ChildMonitoring, RchService::FamilyPlanning], true);
                $amount = $definition->defaultPrice();
                $outdated = $service && $standardize ? $this->prices($service)->where('payer_type', PayerType::Cash->value)
                    ->where(fn ($query) => $query->where('amount', '!=', $amount)->orWhere('currency', '!=', 'TZS'))
                    ->pluck('id')->all() : [];
                $replace = ! $price || in_array($price->id, $outdated, true);

                $attributes = [
                    'code' => $definition->value,
                    'service_type' => $definition->serviceType(),
                    'department_id' => $definition === RchService::Ultrasound && $service?->department_id
                        ? $service->department_id : $department->id,
                    'requires_payment' => $standardize ? $amount > 0 : ($service?->requires_payment ?? true),
                    'is_active' => true,
                ];
                $service?->fill($attributes);
                $serviceChanged = ! $service || $service->trashed() || $service->isDirty();
                $rows[] = [
                    $definition->value,
                    $service?->id ?? 'new',
                    $service ? ($service->trashed() ? 'Deleted' : ($service->getOriginal('is_active') ? 'Active' : 'Inactive')) : 'Missing',
                    $service?->getOriginal('service_type')?->value ?? '—',
                    $service?->getOriginal('department_id') ?? '—',
                    $price ? $pricing->formatMoney($price->amount, $price->currency) : 'Missing',
                    $replace ? $pricing->formatMoney($amount) : $pricing->formatMoney($price->amount, $price->currency),
                    implode(', ', $outdated),
                    $serviceChanged ? 'Configure' : 'Keep',
                ];
                $plans[] = compact('definition', 'service', 'attributes', 'outdated', 'replace', 'serviceChanged', 'price');
            }

            $this->table(['Code', 'Service ID', 'Current state', 'Current type', 'Department ID', 'Current cash', 'Planned cash', 'Cash versions to deactivate', 'Service action'], $rows);
            foreach ($blockers as $blocker) {
                $this->error($blocker);
            }
            if ($blockers !== []) {
                return self::FAILURE;
            }
            if (! $actor) {
                $this->info('Audit only. Review the displayed plan before applying.');

                return self::SUCCESS;
            }

            foreach ($plans as $plan) {
                $this->apply($department, $actor, ...$plan);
            }
            $this->info('RCH catalogue configuration verified and applied.');

            return self::SUCCESS;
        }, 3);
    }

    private function prices(Service $service): Builder
    {
        return ServicePrice::query()->where('facility_id', $service->facility_id)->where('service_id', $service->id)
            ->where('is_active', true)
            ->where(fn ($query) => $query->whereNull('effective_to')->orWhereDate('effective_to', '>=', today()));
    }

    private function apply(Department $department, User $actor, RchService $definition, ?Service $service, array $attributes, array $outdated, bool $replace, bool $serviceChanged, ?ServicePrice $price): void
    {
        if (! $serviceChanged && ! $replace && $outdated === []) {
            return;
        }

        $original = $service?->getRawOriginal();
        if (! $service) {
            $category = ServiceCategory::query()->firstOrCreate(
                ['facility_id' => $department->facility_id, 'code' => 'RCH'],
                ['name' => 'RCH', 'category_type' => 'rch', 'is_active' => true],
            );
            $service = Service::query()->create([
                ...$attributes,
                'facility_id' => $department->facility_id,
                'service_category_id' => $category->id,
                'name' => $definition->catalogueName(),
                'created_by' => $actor->id,
            ]);
        } elseif ($serviceChanged) {
            $service->updated_by = $actor->id;
            $service->trashed() ? $service->restore() : $service->save();
        }

        if ($outdated !== []) {
            $this->prices($service)->whereKey($outdated)->update(['is_active' => false, 'updated_by' => $actor->id]);
        }
        if ($replace) {
            $service->prices()->create([
                'facility_id' => $department->facility_id,
                'payer_type' => PayerType::Cash,
                'amount' => $definition->defaultPrice(),
                'currency' => 'TZS',
                'effective_from' => today(),
                'is_active' => true,
                'created_by' => $actor->id,
            ]);
        }

        ActivityLog::query()->create([
            'user_id' => $actor->id,
            'event' => 'rch_catalogue_configured',
            'subject_type' => Service::class,
            'subject_id' => $service->id,
            'old_values' => ['service' => $original, 'cash_price_id' => $price?->id],
            'new_values' => ['service' => $attributes, 'facility_id' => $department->facility_id, 'deactivated_cash_price_ids' => $outdated],
        ]);
    }
}
