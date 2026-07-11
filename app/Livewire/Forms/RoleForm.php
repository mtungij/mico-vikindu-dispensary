<?php

namespace App\Livewire\Forms;

use App\Models\Role;
use Illuminate\Validation\Rule;
use Livewire\Form;

class RoleForm extends Form
{
    public ?int $id = null;
    public string $name = '';
    public string $display_name = '';
    public ?string $description = null;
    public bool $is_active = true;
    public ?int $copy_from_role_id = null;

    public function setRole(Role $role): void
    {
        $this->id = $role->id;
        $this->name = $role->name;
        $this->display_name = $role->display_name ?? str($role->name)->replace('-', ' ')->title()->toString();
        $this->description = $role->description;
        $this->is_active = $role->is_active;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'display_name' => ['required', 'string', 'max:100'],
            'name' => [
                'required',
                'alpha_dash',
                'max:100',
                Rule::unique('roles', 'name')->where('guard_name', 'web')->ignore($this->id),
            ],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['boolean'],
            'copy_from_role_id' => ['nullable', Rule::exists('roles', 'id')],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function data(): array
    {
        $validated = $this->validate();
        $validated['name'] = str($validated['name'])->slug()->toString();
        $validated['display_name'] = str($validated['display_name'])->trim()->toString();

        return $validated;
    }

    public function resetForm(): void
    {
        $this->reset();
        $this->is_active = true;
    }
}
