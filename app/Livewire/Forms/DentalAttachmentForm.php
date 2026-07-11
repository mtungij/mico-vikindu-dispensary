<?php

namespace App\Livewire\Forms;

use Livewire\Form;

class DentalAttachmentForm extends Form
{
    public ?string $tooth_number = null; public string $attachment_type = 'intraoral_photo'; public string $title = ''; public ?string $description = null; public ?string $captured_at = null;
    public function rules(): array { return ['tooth_number'=>['nullable','string','max:8'],'attachment_type'=>['required','string'],'title'=>['required','string','max:255'],'description'=>['nullable','string'],'captured_at'=>['nullable','date']]; }
    public function validationAttributes(): array { return []; }
    public function normalize(): array { return $this->validate(); }
    public function fillFromModel($m): void { foreach (array_keys($this->rules()) as $k) $this->{$k}=$m->{$k}; }
    public function resetForm(): void { $this->reset(); $this->attachment_type='intraoral_photo'; }
}
