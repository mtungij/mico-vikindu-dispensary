<?php

namespace App\Livewire\Forms;

use Livewire\Form;

class CriticalResultNotificationForm extends Form
{
    public ?int $notified_to_user_id = null; public string $notification_method = 'system'; public ?string $communication_notes = null;
    public function rules(): array { return ['notification_method' => ['required'], 'communication_notes' => ['nullable', 'string']]; }
    public function validationAttributes(): array { return ['notification_method' => 'njia']; }
    public function normalize(): array { return $this->all(); }
    public function fillFromModel($model): void {}
    public function resetForm(): void { $this->reset(); $this->notification_method = 'system'; }
}
