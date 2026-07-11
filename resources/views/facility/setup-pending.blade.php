<x-layouts.auth title="Mfumo unaandaliwa">
    <x-card class="w-full max-w-lg text-center">
        <div class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-lg bg-warning/10 text-warning">
            <x-lucide-triangle-alert class="h-6 w-6" />
        </div>
        <h1 class="text-lg font-semibold">Mfumo bado unaandaliwa</h1>
        <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">Wasiliana na msimamizi ili akamilishe Facility Setup kabla ya kutumia mfumo.</p>
        <form method="POST" action="{{ route('logout') }}" class="mt-5">
            @csrf
            <x-secondary-button type="submit">Toka</x-secondary-button>
        </form>
    </x-card>
</x-layouts.auth>
