<?php

namespace App\Livewire\Profile;

use App\Support\Notifier;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Livewire\Component;

class ChangePassword extends Component
{
    public bool $showModal = false;

    public string $current_password = '';

    public string $password = '';

    public string $password_confirmation = '';

    public function open(): void
    {
        $this->resetValidation();
        $this->reset(['current_password', 'password', 'password_confirmation']);
        $this->showModal = true;
    }

    public function save(): void
    {
        $this->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
        ]);

        auth()->user()?->forceFill([
            'password' => Hash::make($this->password),
        ])->save();

        $this->showModal = false;
        $this->reset(['current_password', 'password', 'password_confirmation']);
        Notifier::success('messages.password_changed');
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            'current_password' => 'nenosiri la sasa',
            'password' => 'nenosiri jipya',
        ];
    }

    public function render(): View
    {
        return view('livewire.profile.change-password');
    }
}
