<?php

namespace App\Livewire\Forms;

use Livewire\Form;

class AppointmentForm extends Form
{
    public ?int $department_id = null;
    public ?int $assigned_to_user_id = null;
    public string $appointment_type = 'opd_follow_up';
    public ?string $scheduled_start = null;
    public ?string $scheduled_end = null;
    public ?string $reason = null;
    public ?string $notes = null;
    public function rules(): array { return ['department_id' => ['nullable', 'integer'], 'assigned_to_user_id' => ['nullable', 'integer'], 'appointment_type' => ['required'], 'scheduled_start' => ['required', 'date', 'after_or_equal:today'], 'scheduled_end' => ['nullable', 'date', 'after:scheduled_start'], 'reason' => ['nullable', 'string'], 'notes' => ['nullable', 'string']]; }
    public function validationAttributes(): array { return ['scheduled_start' => 'tarehe ya follow-up']; }
    public function normalize(): array { return $this->all(); }
    public function fillFromModel($model): void { $this->fill($model->only(array_keys($this->normalize()))); }
    public function resetForm(): void { $this->reset(); $this->appointment_type = 'opd_follow_up'; }
}
