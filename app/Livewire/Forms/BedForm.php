<?php

namespace App\Livewire\Forms;

use Livewire\Form;

class BedForm extends Form { public ?int $id=null; public ?int $observation_room_id=null; public string $name=''; public string $code=''; public string $bed_type='standard'; public ?string $gender_restriction='any'; public ?string $hourly_rate=null; public ?string $session_rate=null; public ?string $daily_rate=null; public string $status='available'; public bool $is_active=true; public string $current_cleaning_status='clean'; public ?string $notes=null; public function rules(): array { return ['observation_room_id'=>['required','integer'],'name'=>['required'],'code'=>['required'],'bed_type'=>['required'],'status'=>['required']]; } public function validationAttributes(): array { return ['name'=>'kitanda']; } public function normalize(): array { return $this->except('id'); } public function fillFromModel($m): void { $this->id=$m->id; $this->fill($m->only(array_keys($this->normalize()))); } public function resetForm(): void { $this->reset(); $this->bed_type='standard'; $this->gender_restriction='any'; $this->status='available'; $this->current_cleaning_status='clean'; $this->is_active=true; } }
