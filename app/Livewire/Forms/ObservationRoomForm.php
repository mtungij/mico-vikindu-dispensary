<?php

namespace App\Livewire\Forms;

use Livewire\Form;

class ObservationRoomForm extends Form { public ?int $id=null; public ?int $department_id=null; public string $name=''; public string $code=''; public string $room_type='general_observation'; public ?string $floor=null; public ?string $location_description=null; public ?string $gender_restriction='any'; public bool $isolation_room=false; public int $capacity=1; public bool $is_active=true; public ?string $notes=null; public function rules(): array { return ['name'=>['required'],'code'=>['required'],'room_type'=>['required'],'capacity'=>['required','integer','min:1']]; } public function validationAttributes(): array { return ['name'=>'room']; } public function normalize(): array { return $this->except('id'); } public function fillFromModel($m): void { $this->id=$m->id; $this->fill($m->only(array_keys($this->normalize()))); } public function resetForm(): void { $this->reset(); $this->room_type='general_observation'; $this->gender_restriction='any'; $this->capacity=1; $this->is_active=true; } }
