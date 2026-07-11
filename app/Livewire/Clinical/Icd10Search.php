<?php

namespace App\Livewire\Clinical;

use App\Models\Icd10Code;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Component;

class Icd10Search extends Component
{
    public string $query = '';
    public function selectCode(string $code, string $title): void { $this->dispatch('icd10-selected', code: $code, title: $title); }
    public function render(): View
    {
        $results = strlen($this->query) >= 2 ? Icd10Code::query()->search($this->query)->orderBy('code')->limit(20)->get() : new Collection();
        return view('livewire.clinical.icd10-search', ['results' => $results]);
    }
}
