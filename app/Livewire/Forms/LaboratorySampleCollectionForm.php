<?php

namespace App\Livewire\Forms;

use Livewire\Form;

class LaboratorySampleCollectionForm extends Form
{
    public array $order_item_ids = [];

    public ?int $specimen_type_id = null;

    public ?string $container_type = null;

    public ?string $collected_at = null;

    public ?string $volume_collected = null;

    public ?string $volume_unit = null;

    public ?string $collection_location = null;

    public ?string $collection_notes = null;

    public function rules(): array
    {
        return [
            'order_item_ids' => ['required', 'array', 'min:1'],
            'order_item_ids.*' => ['integer', 'distinct'],
            'specimen_type_id' => ['nullable', 'integer'],
            'collected_at' => ['nullable', 'date'],
            'volume_collected' => ['nullable', 'numeric'],
        ];
    }

    public function messages(): array
    {
        return [
            'order_item_ids.required' => 'Chagua angalau kipimo kimoja cha kukusanyia sampuli.',
            'order_item_ids.min' => 'Chagua angalau kipimo kimoja cha kukusanyia sampuli.',
        ];
    }

    public function validationAttributes(): array
    {
        return ['specimen_type_id' => 'specimen type'];
    }

    public function normalize(): array
    {
        return $this->all();
    }

    public function fillFromModel($model): void {}

    public function resetForm(): void
    {
        $this->reset();
    }
}
