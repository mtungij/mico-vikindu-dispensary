<?php

namespace App\Livewire\Administration\ClinicalCatalogues;

use App\Models\ActivityLog;
use App\Models\Icd10Code;
use App\Services\Icd10ImportService;
use App\Support\Notifier;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

class Icd10Index extends Component
{
    use WithFileUploads;
    use WithPagination;

    public string $search = '';

    public ?TemporaryUploadedFile $csvFile = null;

    public string $source = '';

    public string $sourceVersion = '';

    public bool $dryRun = true;

    public ?array $importResult = null;

    public function mount(): void
    {
        Gate::authorize('icd10.view');
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function import(Icd10ImportService $importer): void
    {
        Gate::authorize('icd10.import');
        $this->validate([
            'csvFile' => ['required', 'file', 'mimes:csv,txt', 'max:51200'],
            'source' => ['required', 'string', 'max:255'],
            'sourceVersion' => ['nullable', 'string', 'max:100'],
            'dryRun' => ['boolean'],
        ]);

        try {
            $this->importResult = $importer->import(
                path: $this->csvFile->getRealPath(),
                source: $this->source,
                version: $this->sourceVersion,
                dryRun: $this->dryRun,
                actor: auth()->user(),
            );
        } catch (InvalidArgumentException $exception) {
            $this->addError('csvFile', $exception->getMessage());

            return;
        }

        if (! $this->dryRun) {
            $this->csvFile = null;
        }

        Notifier::success($this->dryRun ? 'ICD-10 dry run completed.' : 'ICD-10 catalogue imported.');
    }

    public function toggleActive(int $id): void
    {
        Gate::authorize('icd10.manage');
        $code = Icd10Code::query()->findOrFail($id);
        $code->update(['is_active' => ! $code->is_active]);
        $this->auditCodeChange($code, 'is_active');
    }

    public function toggleBillable(int $id): void
    {
        Gate::authorize('icd10.manage');
        $code = Icd10Code::query()->findOrFail($id);
        $code->update(['is_billable' => ! $code->is_billable]);
        $this->auditCodeChange($code, 'is_billable');
    }

    public function render(): View
    {
        $term = trim($this->search);
        $query = Icd10Code::query()
            ->when($term !== '', fn ($query) => $query->where(function ($query) use ($term): void {
                $query->where('code', 'like', "%{$term}%")
                    ->orWhere('title', 'like', "%{$term}%")
                    ->orWhere('description', 'like', "%{$term}%");
            }))
            ->orderBy('code');
        $total = Icd10Code::query()->count();
        $active = Icd10Code::query()->where('is_active', true)->count();
        $developmentSamplesOnly = $active === 10
            && ! Icd10Code::query()->where('is_active', true)->where(function ($query): void {
                $query->whereNull('chapter')->orWhere('chapter', '!=', 'Development sample');
            })->exists();
        $lastImport = ActivityLog::query()->where('event', 'icd10.catalogue_imported')->latest()->first();

        return view('livewire.administration.clinical-catalogues.icd10-index', [
            'codes' => $query->paginate(25),
            'total' => $total,
            'active' => $active,
            'inactive' => $total - $active,
            'developmentSamplesOnly' => $developmentSamplesOnly,
            'lastImport' => $lastImport,
        ])->layout('components.layouts.app', [
            'title' => 'ICD-10 Catalogue Management',
            'description' => 'Manage the global approved clinical diagnosis catalogue.',
        ]);
    }

    private function auditCodeChange(Icd10Code $code, string $field): void
    {
        ActivityLog::query()->create([
            'user_id' => auth()->id(),
            'event' => 'icd10.code_updated',
            'subject_type' => Icd10Code::class,
            'subject_id' => $code->id,
            'new_values' => ['code' => $code->code, $field => $code->{$field}],
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }
}
