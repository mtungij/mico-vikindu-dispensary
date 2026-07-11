<x-layouts.auth title="Ingia">
    @php($facility = app(\App\Services\FacilityContext::class)->current())
    <div class="w-full max-w-md">
        <div class="mb-6 text-center">
            <x-facility-logo :facility="$facility" class="mx-auto h-14 w-14" />
            <h1 class="mt-4 text-xl font-semibold">{{ $facility?->name ?? 'Professional Dispensary Management System' }}</h1>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Ingia kwenye mfumo</p>
        </div>
        <x-card>
            @if (session('status'))
                <div class="mb-4 rounded-md bg-green-50 p-3 text-sm text-success dark:bg-green-950/30">{{ session('status') }}</div>
            @endif
            <form method="POST" action="{{ route('login') }}" class="space-y-4" x-data="{ showPassword: false }">
                @csrf
                <div>
                    <x-input-label for="email" value="Barua pepe" />
                    <x-text-input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="username" class="mt-1" />
                    <x-input-error :messages="$errors->get('email')" />
                </div>
                <div>
                    <x-input-label for="password" value="Nenosiri" />
                    <div class="relative mt-1">
                        <x-text-input id="password" name="password" x-bind:type="showPassword ? 'text' : 'password'" required autocomplete="current-password" class="pr-10" />
                        <button type="button" @click="showPassword = !showPassword" class="absolute inset-y-0 right-0 flex w-10 items-center justify-center text-slate-500">
                            <x-lucide-eye class="h-4 w-4" />
                        </button>
                    </div>
                    <x-input-error :messages="$errors->get('password')" />
                </div>
                <div class="flex items-center justify-between">
                    <label class="inline-flex items-center gap-2 text-sm">
                        <x-checkbox name="remember" value="1" />
                        <span>Nikumbuke</span>
                    </label>
                    <a href="{{ route('password.request') }}" class="text-sm font-medium text-primary hover:underline">Umesahau nenosiri?</a>
                </div>
                <x-primary-button class="w-full" wire:loading.attr="disabled">
                    <x-lucide-log-in class="h-4 w-4" />
                    <span>Ingia kwenye mfumo</span>
                </x-primary-button>
            </form>
        </x-card>
    </div>
</x-layouts.auth>
