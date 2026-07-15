<?php

namespace App\Livewire\Rch;

use App\Models\AncVisit;
use App\Models\ChildGrowthMeasurement;
use App\Models\ChildNutritionAssessment;
use App\Models\FamilyPlanningVisit;
use App\Models\ImmunizationAdministration;
use App\Models\Pregnancy;
use App\Models\RchChild;
use App\Models\RchEncounter;
use App\Models\Visit;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class Dashboard extends Component
{
    public function mount(): void { Gate::authorize('rch.view-dashboard'); }
    public function render(): View
    {
        $facilityId = currentFacility()?->id;
        $cards = [
            'RCH Patients Today' => Visit::query()->forCurrentFacility()->whereHas('destinationDepartment', fn($q) => $q->where('code', 'RCH'))->whereDate('registered_at', today())->count(),
            'ANC Visits Today' => AncVisit::query()->where('facility_id', $facilityId)->whereDate('visit_date', today())->count(),
            'Active Pregnancies' => Pregnancy::query()->where('facility_id', $facilityId)->where('status', 'active')->count(),
            'High-risk Pregnancies' => Pregnancy::query()->where('facility_id', $facilityId)->where('high_risk', true)->count(),
            'Family Planning Visits' => FamilyPlanningVisit::query()->where('facility_id', $facilityId)->whereDate('visit_date', today())->count(),
            'Children Seen Today' => ChildGrowthMeasurement::query()->where('facility_id', $facilityId)->whereDate('measured_at', today())->distinct('rch_child_id')->count('rch_child_id'),
            'Nutrition Alerts' => ChildNutritionAssessment::query()->where('facility_id', $facilityId)->whereIn('overall_nutrition_status', ['severe_acute_malnutrition','severely_underweight','severely_wasted'])->count(),
            'Vaccines Due' => 0,
            'Vaccines Overdue' => 0,
            'RCH Appointments Today' => 0,
            'RCH Referrals' => RchEncounter::query()->where('facility_id', $facilityId)->where('status', 'referred')->count(),
            'RCH Revenue Today' => 0,
        ];
        return view('livewire.rch.dashboard', [
            'cards' => $cards,
            'highRisk' => Pregnancy::query()->where('facility_id', $facilityId)->where('high_risk', true)->with('patient')->latest()->limit(6)->get(),
            'recentChildren' => RchChild::query()->where('facility_id', $facilityId)->with('patient')->latest()->limit(6)->get(),
        ])->layout('components.layouts.app', ['title' => 'RCH Dashboard', 'description' => 'Reproductive and child health overview.']);
    }
}
