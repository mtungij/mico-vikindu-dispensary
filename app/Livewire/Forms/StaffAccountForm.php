<?php

namespace App\Livewire\Forms;

use App\Enums\UserStatus;
use Illuminate\Validation\Rule;
use Livewire\Form;

class StaffAccountForm extends Form
{
    public bool $create_login_account = true;
    public string $email = '';
    public ?string $phone = null;
    public ?string $temporary_password = null;
    public ?string $temporary_password_confirmation = null;
    public string $status = 'active';
    public bool $must_change_password = true;
    public array $role_ids = [];
    public array $direct_permissions = [];

    public function rules(): array
    {
        return [
            'create_login_account' => ['boolean'],
            'email' => ['required', 'email:rfc', 'max:150', Rule::unique('users', 'email')],
            'phone' => ['nullable', 'string', 'max:30'],
            'temporary_password' => ['required', 'string', 'min:8', 'confirmed'],
            'status' => ['required', Rule::enum(UserStatus::class)],
            'must_change_password' => ['boolean'],
            'role_ids' => ['array', 'min:1'],
            'role_ids.*' => ['integer', Rule::exists('roles', 'id')],
            'direct_permissions' => ['array'],
            'direct_permissions.*' => ['string', Rule::exists('permissions', 'name')],
        ];
    }

    public function data(): array
    {
        $data = $this->validate();
        $data['email'] = str($data['email'])->lower()->trim()->toString();

        return $data;
    }
}
