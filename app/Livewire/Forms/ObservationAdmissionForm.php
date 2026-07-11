<?php

namespace App\Livewire\Forms;

use Livewire\Form;

class ObservationAdmissionForm extends Form { public ?int $patient_id=null; public ?int $visit_id=null; public ?int $bed_id=null; public ?int $service_id=null; public string $admission_type='hourly_observation'; public string $reason_for_admission=''; public ?string $provisional_diagnosis=null; public ?string $expected_discharge_at=null; public ?string $acuity_level='low'; public bool $isolation_required=false; public bool $guardian_required=false; public ?string $guardian_name=null; public ?string $guardian_phone=null; public ?string $diet_instruction=null; public ?string $mobility_status=null; public ?string $fall_risk=null; public ?string $infection_risk=null; public ?string $notes=null; public ?string $override_reason=null; public function rules(): array { return ['patient_id'=>['required','integer'],'visit_id'=>['required','integer'],'admission_type'=>['required'],'reason_for_admission'=>['required']]; } public function validationAttributes(): array { return ['reason_for_admission'=>'sababu ya kulazwa']; } public function normalize(): array { return $this->all(); } public function fillFromModel($m): void {} public function resetForm(): void { $this->reset(); $this->admission_type='hourly_observation'; $this->acuity_level='low'; } }
