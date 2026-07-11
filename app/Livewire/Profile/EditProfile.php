<?php

namespace App\Livewire\Profile;

use App\Support\Notifier;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Component;

class EditProfile extends Component
{
    public bool $showModal = false;

    public string $name = '';

    public string $email = '';

    public ?string $phone = null;

    public function mount(): void
    {
        $user = auth()->user();

        $this->name = (string) $user?->name;
        $this->email = (string) $user?->email;
        $this->phone = $user?->phone;
    }

    public function edit(): void
    {
        $this->showModal = true;
    }

    public function save(): void
    {
        $user = auth()->user();

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email:rfc', 'max:255', Rule::unique('users', 'email')->ignore($user?->id)],
            'phone' => ['nullable', 'regex:/^[0-9+\s-]{9,20}$/'],
        ]);

        DB::transaction(fn () => $user?->update($validated));

        $this->showModal = false;
        Notifier::success('messages.updated');
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            'name' => 'jina',
            'email' => 'barua pepe',
            'phone' => 'namba ya simu',
        ];
    }

    public function render(): View
    {
        return view('livewire.profile.edit-profile');
    }
}
