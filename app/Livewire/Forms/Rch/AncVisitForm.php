<?php

namespace App\Livewire\Forms\Rch;

use Livewire\Form;

class AncVisitForm extends Form
{
    public ?string $visit_date = null; public ?float $weight_kg = null; public ?int $systolic_bp = null; public ?int $diastolic_bp = null; public ?float $hemoglobin = null; public ?int $fetal_heart_rate = null; public ?string $assessment = null; public ?string $plan = null; public ?string $next_visit_date = null; public string $status = 'completed';
    public function rules(): array { return ['visit_date'=>'required|date','weight_kg'=>'nullable|numeric|min:20|max:250','systolic_bp'=>'nullable|integer|min:50|max:260','diastolic_bp'=>'nullable|integer|min:30|max:180','hemoglobin'=>'nullable|numeric|min:2|max:25','fetal_heart_rate'=>'nullable|integer|min:40|max:240','assessment'=>'nullable|string','plan'=>'nullable|string','next_visit_date'=>'nullable|date|after:visit_date','status'=>'required|string|max:40']; }
    public function validationAttributes(): array { return ['next_visit_date'=>'next visit date']; }
    public function normalize(): array { $data = $this->validate(); $data['visit_date'] ??= today()->toDateString(); return $data; }
    public function fillFromModel($model): void { $this->fill($model->only(array_keys($this->rules()))); }
    public function resetForm(): void { $this->reset(); $this->status = 'completed'; }
}
