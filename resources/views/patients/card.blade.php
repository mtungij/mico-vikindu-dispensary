<x-layouts.app title="Patient Card" description="Preview the patient's hospital identity card before printing.">
    <x-slot:actions>
        <a href="{{ route('patients.show', $patient) }}" class="inline-flex items-center justify-center gap-2 rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100 dark:hover:bg-slate-800">
            <x-lucide-arrow-left class="h-4 w-4" /> Back to Patient
        </a>
        <a href="{{ route('patients.card.print', $patient) }}" target="_blank" rel="noopener" class="inline-flex items-center justify-center gap-2 rounded-md bg-primary px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-teal-800">
            <x-lucide-printer class="h-4 w-4" /> Print Patient Card
        </a>
    </x-slot:actions>

    @include('patients.partials.card-styles')

    <div class="patient-card-workspace">
        <div class="patient-card-preview-heading">
            <span class="patient-card-preview-icon"><x-lucide-id-card /></span>
            <div>
                <h2>Card preview</h2>
                <p>The printed card uses the standard CR80 portrait size (53.98 × 85.60 mm).</p>
            </div>
        </div>

        @include('patients.partials.identity-card')
    </div>
</x-layouts.app>
