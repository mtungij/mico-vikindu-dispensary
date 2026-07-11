<?php

namespace App\Livewire\Forms;

use App\Models\SpecimenType;
use Livewire\Form;

class SpecimenTypeForm extends Form
{
    public ?int $id = null; public string $name = ''; public string $code = ''; public ?string $description = null; public ?string $container_type = null; public ?string $collection_instructions = null; public ?string $minimum_volume = null; public ?string $volume_unit = null; public ?string $storage_temperature = null; public ?string $transport_instructions = null; public ?string $rejection_criteria = null; public bool $is_active = true; public int $sort_order = 0;
    public function rules(): array { return ['name' => ['required'], 'code' => ['required'], 'minimum_volume' => ['nullable', 'numeric'], 'is_active' => ['boolean']]; }
    public function validationAttributes(): array { return ['name' => 'jina', 'code' => 'code']; }
    public function normalize(): array { return ['name' => $this->name, 'code' => str($this->code)->upper()->toString(), 'description' => $this->description, 'container_type' => $this->container_type, 'collection_instructions' => $this->collection_instructions, 'minimum_volume' => $this->minimum_volume, 'volume_unit' => $this->volume_unit, 'storage_temperature' => $this->storage_temperature, 'transport_instructions' => $this->transport_instructions, 'rejection_criteria' => $this->rejection_criteria, 'is_active' => $this->is_active, 'sort_order' => $this->sort_order]; }
    public function fillFromModel(SpecimenType $model): void { $this->id = $model->id; $this->fill($model->only(array_keys($this->normalize()))); }
    public function resetForm(): void { $this->reset(); $this->is_active = true; }
}
