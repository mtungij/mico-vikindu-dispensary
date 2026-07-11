<?php

namespace App\Livewire\Demo;

use App\Support\Notifier;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Livewire\WithPagination;

class ModalCrudExample extends Component
{
    use WithPagination;

    public bool $showModal = false;

    public bool $showConfirmModal = false;

    public bool $editing = false;

    public ?int $editingId = null;

    public ?int $deletingId = null;

    public string $name = '';

    public string $email = '';

    /**
     * @var array<int, array{id: int, name: string, email: string}>
     */
    public array $records = [
        ['id' => 1, 'name' => 'Mfano Mtumishi', 'email' => 'demo@example.test'],
    ];

    public function create(): void
    {
        $this->resetForm();
        $this->showModal = true;
    }

    public function edit(int $id): void
    {
        $record = collect($this->records)->firstWhere('id', $id);

        if ($record === null) {
            Notifier::error();
            return;
        }

        $this->editing = true;
        $this->editingId = $id;
        $this->name = $record['name'];
        $this->email = $record['email'];
        $this->showModal = true;
    }

    public function save(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email:rfc', 'max:255'],
        ]);

        if ($this->editing && $this->editingId !== null) {
            foreach ($this->records as $index => $record) {
                if ($record['id'] === $this->editingId) {
                    $this->records[$index] = ['id' => $this->editingId, ...$validated];
                }
            }

            Notifier::success('messages.updated');
        } else {
            $this->records[] = ['id' => count($this->records) + 1, ...$validated];
            Notifier::success();
        }

        $this->closeModal();
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->resetForm();
    }

    public function confirmDelete(int $id): void
    {
        $this->deletingId = $id;
        $this->showConfirmModal = true;
    }

    public function delete(): void
    {
        $this->records = array_values(array_filter(
            $this->records,
            fn (array $record): bool => $record['id'] !== $this->deletingId,
        ));

        $this->showConfirmModal = false;
        $this->deletingId = null;
        Notifier::success('messages.deleted');
    }

    public function resetForm(): void
    {
        $this->resetValidation();
        $this->editing = false;
        $this->editingId = null;
        $this->name = '';
        $this->email = '';
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            'name' => 'jina',
            'email' => 'barua pepe',
        ];
    }

    public function render(): View
    {
        return view('livewire.demo.modal-crud-example');
    }
}
