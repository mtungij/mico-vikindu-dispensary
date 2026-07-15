<?php

namespace App\Services;

use App\Models\ImmunizationAdministration;
use App\Models\ImmunizationSchedule;
use App\Models\ImmunizationScheduleItem;
use App\Models\RchChild;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

class ImmunizationScheduleService
{
    public function getApplicableSchedule(RchChild $child): ?ImmunizationSchedule { return ImmunizationSchedule::query()->where('facility_id', $child->facility_id)->where('is_active', true)->where('target_group', 'child')->orderByDesc('is_default')->first(); }
    public function getDueVaccines(RchChild $child) { return $this->classify($child, 'due'); }
    public function getOverdueVaccines(RchChild $child) { return $this->classify($child, 'overdue'); }
    public function getCompletedVaccines(RchChild $child) { return ImmunizationAdministration::query()->where('rch_child_id', $child->id)->where('status', 'administered')->get(); }
    public function calculateNextDueDate(RchChild $child, ImmunizationScheduleItem $item): CarbonImmutable { return CarbonImmutable::parse($child->birth_date)->addDays((int) ($item->recommended_age_days ?? 0)); }
    public function buildCatchUpPlan(RchChild $child) { return $this->getOverdueVaccines($child); }
    public function classifyDoseStatus(RchChild $child, ImmunizationScheduleItem $item): string
    {
        $done = ImmunizationAdministration::query()->where('rch_child_id', $child->id)->where('vaccine_id', $item->vaccine_id)->where('dose_number', $item->dose_number)->where('status', 'administered')->exists();
        if ($done) return 'completed';
        $due = $this->calculateNextDueDate($child, $item);
        return $due->isFuture() ? 'not_due' : ($due->lessThan(today()->subDays(28)) ? 'overdue' : 'due');
    }
    public function validateDoseInterval(RchChild $child, ImmunizationScheduleItem $item): void
    {
        if (ImmunizationAdministration::query()->where('rch_child_id', $child->id)->where('vaccine_id', $item->vaccine_id)->where('dose_number', $item->dose_number)->where('status', 'administered')->exists()) {
            throw ValidationException::withMessages(['vaccine_id' => 'Dose hii tayari imetolewa.']);
        }
    }
    public function getDefaulters() { return RchChild::query()->forCurrentFacility()->with('patient')->get()->filter(fn ($child) => $this->getOverdueVaccines($child)->isNotEmpty()); }
    private function classify(RchChild $child, string $status) { $schedule = $this->getApplicableSchedule($child); if (! $schedule) return collect(); return $schedule->items()->with('vaccine')->get()->filter(fn ($item) => $this->classifyDoseStatus($child, $item) === $status)->values(); }
}
