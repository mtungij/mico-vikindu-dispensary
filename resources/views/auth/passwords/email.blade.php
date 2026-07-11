<x-layouts.auth title="Umesahau nenosiri">
    <div class="w-full max-w-md">
        <x-card>
            <h1 class="text-lg font-semibold">Umesahau nenosiri?</h1>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Weka barua pepe ili tutume kiungo cha kuweka nenosiri jipya.</p>
            @if (session('status'))<div class="mt-4 rounded-md bg-green-50 p-3 text-sm text-success dark:bg-green-950/30">{{ session('status') }}</div>@endif
            <form method="POST" action="{{ route('password.email') }}" class="mt-5 space-y-4">
                @csrf
                <div>
                    <x-input-label for="email" value="Barua pepe" />
                    <x-text-input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus class="mt-1" />
                    <x-input-error :messages="$errors->get('email')" />
                </div>
                <div class="flex items-center justify-between">
                    <a href="{{ route('login') }}" class="text-sm text-slate-500 hover:text-primary">Rudi login</a>
                    <x-primary-button>Tuma kiungo</x-primary-button>
                </div>
            </form>
        </x-card>
    </div>
</x-layouts.auth>
