<?php

namespace App\Livewire\Rch;

use App\Models\AncVisit;
use App\Models\ChildNutritionAssessment;
use App\Models\FamilyPlanningClient;
use App\Models\ImmunizationAdministration;
use App\Models\Pregnancy;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class Reports extends Component
{
    public string $report = 'anc_first_visits';
    public function mount(): void { Gate::authorize('rch.reports.view'); }
    public function exportCsv(): mixed
    {
        Gate::authorize('rch.reports.export');
        $rows = $this->rows();
        $csv = collect($rows)->map(fn($row) => collect($row)->map(fn($v) => '"'.str_replace('"', '""', (string) $v).'"')->implode(','))->prepend('Report,Value')->implode("\n");
        return response()->streamDownload(fn() => print($csv), 'rch-report.csv', ['Content-Type' => 'text/csv']);
    }
    public function render(): View { return view('livewire.rch.reports', ['rows'=>$this->rows()])->layout('components.layouts.app', ['title'=>'RCH Reports']); }
    private function rows(): array
    {
        $facilityId = currentFacility()?->id;
        return [
            ['ANC First Visits', AncVisit::query()->where('facility_id', $facilityId)->where('anc_visit_number', 1)->count()],
            ['High-risk Pregnancies', Pregnancy::query()->where('facility_id', $facilityId)->where('high_risk', true)->count()],
            ['Family Planning Clients', FamilyPlanningClient::query()->where('facility_id', $facilityId)->count()],
            ['Nutrition Alerts', ChildNutritionAssessment::query()->where('facility_id', $facilityId)->where('referral_required', true)->count()],
            ['Vaccines Administered', ImmunizationAdministration::query()->where('facility_id', $facilityId)->where('status', 'administered')->count()],
        ];
    }
}
