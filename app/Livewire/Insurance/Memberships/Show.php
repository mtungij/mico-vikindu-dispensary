<?php
namespace App\Livewire\Insurance\Memberships;
use App\Models\PatientInsuranceMembership;
use App\Services\InsuranceEligibilityService;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;
class Show extends Component { public PatientInsuranceMembership $membership; public array $summary=[]; public function mount(PatientInsuranceMembership $membership, InsuranceEligibilityService $eligibility): void { Gate::authorize('view',$membership); $this->membership=$membership->load(['patient','provider','scheme','benefitPackage','dependants.patient','verifications']); $this->summary=$eligibility->buildEligibilitySummary($membership); } public function render(){ return view('livewire.insurance.memberships.show')->layout('components.layouts.app',['title'=>'Membership '.$this->membership->membership_number,'description'=>'Verification and eligibility summary.']); } }
