<?php

namespace App\Livewire\Clinical;

use App\Models\Icd10Code;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Component;

class Icd10Search extends Component
{
    public string $query = '';

    public bool $catalogueAvailable = false;

    public bool $showResults = false;

    public bool $developmentSamplesOnly = false;

    public function mount(): void
    {
        $this->catalogueAvailable = Icd10Code::query()->where('is_active', true)->exists();
        $activeCount = Icd10Code::query()->where('is_active', true)->count();
        $this->developmentSamplesOnly = $activeCount === 10
            && ! Icd10Code::query()->where('is_active', true)->where(function ($query): void {
                $query->whereNull('chapter')->orWhere('chapter', '!=', 'Development sample');
            })->exists();
    }

    public function updatedQuery(): void
    {
        $this->showResults = mb_strlen(trim($this->query)) >= 2;
    }

    public function selectCode(int $id): void
    {
        $icd10Code = Icd10Code::query()->where('is_active', true)->findOrFail($id);

        $this->query = "{$icd10Code->code} — {$icd10Code->title}";
        $this->showResults = false;
        $this->dispatch('icd10-selected', code: $icd10Code->code, title: $icd10Code->title);
    }

    public function render(): View
    {
        $term = trim($this->query);
        $results = $this->catalogueAvailable && $this->showResults && mb_strlen($term) >= 2
            ? Icd10Code::query()->search($term)->limit(20)->get()
            : new Collection;

        return view('livewire.clinical.icd10-search', ['results' => $results]);
    }
}
