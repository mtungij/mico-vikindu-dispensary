<?php

namespace App\Livewire\CorporateAccounts;

use App\Models\CorporateAccount;
use App\Support\Notifier;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;
    public string $name=''; public string $code=''; public ?string $contact_person=null; public bool $showModal=false; public ?int $editingId=null;
    public function mount(): void { Gate::authorize('corporate-accounts.view'); }
    public function create(): void { Gate::authorize('corporate-accounts.create'); $this->reset(['name','code','contact_person','editingId']); $this->showModal=true; }
    public function save(): void { $data=$this->validate(['name'=>['required','max:150'],'code'=>['required','alpha_dash','max:40',Rule::unique('corporate_accounts','code')->where('facility_id',currentFacility()?->id)->ignore($this->editingId)],'contact_person'=>['nullable','max:150']]); CorporateAccount::query()->updateOrCreate(['id'=>$this->editingId], [...$data,'code'=>str($data['code'])->upper(),'facility_id'=>currentFacility()?->id,'created_by'=>auth()->id(),'updated_by'=>auth()->id()]); $this->showModal=false; Notifier::success('messages.saved'); }
    public function render(): View { return view('livewire.corporate-accounts.index',['accounts'=>CorporateAccount::query()->forCurrentFacility()->latest()->paginate(10)])->layout('components.layouts.app',['title'=>'Corporate Accounts','description'=>'Corporate payer foundation.']); }
}
