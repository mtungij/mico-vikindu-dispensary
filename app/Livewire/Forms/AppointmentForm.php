<?php

namespace App\Livewire\Forms;

use Livewire\Form;

class AppointmentForm extends Form
{
    public ?int $patient_id = null;
    public ?int $department_id = null;
    public ?int $staff_id = null;
    public ?int $assigned_to_user_id = null;
    public ?int $service_id = null;
    public string $appointment_type = 'general_consultation';
    public ?string $appointment_date = null;
    public ?string $appointment_time = null;
    public string $estimated_duration = '30';
    public string $priority = 'normal';
    public ?string $reason = null;
    public ?string $notes = null;

    public function rules(): array
    {
        return [
            'patient_id' => ['required', 'integer'],
            'department_id' => ['required', 'integer'],
            'staff_id' => ['nullable', 'integer'],
            'assigned_to_user_id' => ['nullable', 'integer'],
            'service_id' => ['nullable', 'integer'],
            'appointment_type' => ['required', 'string'],
            'appointment_date' => ['required', 'date', 'after_or_equal:today'],
            'appointment_time' => ['required', 'date_format:H:i'],
            'estimated_duration' => ['required', 'integer', 'min:5', 'max:480'],
            'priority' => ['required', 'in:normal,urgent,emergency'],
            'reason' => ['nullable', 'string', 'max:1000'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function validationAttributes(): array
    {
        return [
            'patient_id' => 'patient',
            'department_id' => 'department',
            'appointment_date' => 'appointment date',
            'appointment_time' => 'appointment time',
        ];
    }

    public function normalize(): array
    {
        $data = $this->all();
        $data['assigned_to_user_id'] = $this->staff_id ?: $this->assigned_to_user_id;
        $data['estimated_duration'] = (int) $this->estimated_duration;

        return $data;
    }

    public function fillFromModel($model): void
    {
        $this->patient_id = $model->patient_id;
        $this->department_id = $model->department_id;
        $this->staff_id = $model->staff_id ?: $model->assigned_to_user_id;
        $this->assigned_to_user_id = $model->assigned_to_user_id;
        $this->service_id = $model->service_id;
        $this->appointment_type = $model->appointment_type?->value ?? (string) $model->appointment_type;
        $this->appointment_date = $model->appointment_date?->toDateString() ?? $model->scheduled_start?->toDateString();
        $this->appointment_time = $model->appointment_time ? substr((string) $model->appointment_time, 0, 5) : $model->scheduled_start?->format('H:i');
        $this->estimated_duration = (string) ($model->estimated_duration ?: 30);
        $this->priority = $model->priority ?: 'normal';
        $this->reason = $model->reason;
        $this->notes = $model->notes;
    }

    public function resetForm(): void
    {
        $this->reset();
        $this->appointment_type = 'general_consultation';
        $this->estimated_duration = '30';
        $this->priority = 'normal';
    }
}
