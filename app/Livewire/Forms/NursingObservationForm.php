<?php

namespace App\Livewire\Forms;

use Livewire\Form;

class NursingObservationForm extends Form { public ?string $general_condition=null; public ?string $consciousness_level=null; public ?int $pain_score=null; public ?string $temperature=null; public ?int $systolic_bp=null; public ?int $diastolic_bp=null; public ?int $pulse_rate=null; public ?int $respiratory_rate=null; public ?string $oxygen_saturation=null; public ?string $blood_glucose=null; public ?string $intake_summary=null; public ?string $output_summary=null; public ?string $mobility_status=null; public ?string $fall_risk=null; public ?string $skin_condition=null; public ?string $wound_status=null; public ?string $notes=null; public function rules(): array { return ['pain_score'=>['nullable','integer','between:0,10'],'temperature'=>['nullable','numeric'],'systolic_bp'=>['nullable','integer'],'diastolic_bp'=>['nullable','integer']]; } public function validationAttributes(): array { return ['pain_score'=>'pain score']; } public function normalize(): array { return $this->all(); } public function fillFromModel($m): void { $this->fill($m->only(array_keys($this->normalize()))); } public function resetForm(): void { $this->reset(); } }
