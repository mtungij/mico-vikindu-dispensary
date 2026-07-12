<?php
namespace App\Livewire\Insurance;

use App\Models\InsuranceClaim;
use App\Models\InsurancePayment;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class Dashboard extends Component
{
    public function mount(): void { Gate::authorize('insurance.view-dashboard'); }
    public function render()
    {
        $facilityId = currentFacility()?->id;
        return view('livewire.insurance.dashboard', [
            'stats' => [
                'draft' => InsuranceClaim::where('facility_id',$facilityId)->where('status','draft')->count(),
                'pending_validation' => InsuranceClaim::where('facility_id',$facilityId)->where('status','pending_validation')->count(),
                'ready' => InsuranceClaim::where('facility_id',$facilityId)->where('status','ready')->count(),
                'submitted' => InsuranceClaim::where('facility_id',$facilityId)->whereIn('status',['submitted','under_review'])->count(),
                'rejected' => InsuranceClaim::where('facility_id',$facilityId)->where('status','rejected')->count(),
                'outstanding' => InsuranceClaim::where('facility_id',$facilityId)->sum('outstanding_amount'),
                'payments_month' => InsurancePayment::where('facility_id',$facilityId)->whereMonth('payment_date', now()->month)->sum('amount'),
            ],
            'attention' => InsuranceClaim::where('facility_id',$facilityId)->whereIn('status',['validation_failed','rejected','correction_required'])->with('patient')->latest()->limit(8)->get(),
        ])->layout('components.layouts.app', ['title' => 'Insurance Dashboard', 'description' => 'Muhtasari wa madai, malipo na reconciliation.']);
    }
}
