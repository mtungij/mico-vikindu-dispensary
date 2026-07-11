<?php

namespace App\Livewire\Services;

use App\Enums\PayerType;
use App\Livewire\Forms\ServicePriceForm;
use App\Models\CorporateAccount;
use App\Models\InsuranceProvider;
use App\Models\Service;
use App\Services\ServicePricingService;
use App\Support\Notifier;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class ManagePrices extends Component
{
    public Service $service; public ServicePriceForm $form; public bool $showModal = false;
    public function mount(Service $service): void { Gate::authorize('managePrices', $service); $this->service = $service->load('prices'); }
    public function create(): void { $this->form->reset(); $this->form->payer_type = 'cash'; $this->showModal = true; }
    public function save(ServicePricingService $pricing): void { Gate::authorize('managePrices', $this->service); $pricing->createPriceVersion($this->service, $this->form->data(), auth()->user()); $this->showModal = false; $this->service = $this->service->refresh()->load('prices'); Notifier::success('messages.saved'); }
    public function render(): View { return view('livewire.services.manage-prices', ['prices' => $this->service->prices()->latest()->get(), 'payerTypes' => PayerType::cases(), 'providers' => InsuranceProvider::query()->forCurrentFacility()->get(), 'corporates' => CorporateAccount::query()->forCurrentFacility()->get()])->layout('components.layouts.app', ['title' => 'Service Prices', 'description' => $this->service->name]); }
}
