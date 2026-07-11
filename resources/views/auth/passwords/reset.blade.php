<x-layouts.auth title="Weka nenosiri jipya">
    <div class="w-full max-w-md">
        <x-card>
            <h1 class="text-lg font-semibold">Weka nenosiri jipya</h1>
            <form method="POST" action="{{ route('password.store') }}" class="mt-5 space-y-4">
                @csrf
                <input type="hidden" name="token" value="{{ $request->route('token') }}">
                <div>
                    <x-input-label for="email" value="Barua pepe" />
                    <x-text-input id="email" name="email" type="email" value="{{ old('email', $request->email) }}" required class="mt-1" />
                    <x-input-error :messages="$errors->get('email')" />
                </div>
                <div>
                    <x-input-label for="password" value="Nenosiri jipya" />
                    <x-text-input id="password" name="password" type="password" required class="mt-1" />
                    <x-input-error :messages="$errors->get('password')" />
                </div>
                <div>
                    <x-input-label for="password_confirmation" value="Rudia nenosiri" />
                    <x-text-input id="password_confirmation" name="password_confirmation" type="password" required class="mt-1" />
                </div>
                <x-primary-button class="w-full">Hifadhi nenosiri</x-primary-button>
            </form>
        </x-card>
    </div>
</x-layouts.auth>
