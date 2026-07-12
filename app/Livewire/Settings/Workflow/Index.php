<?php

namespace App\Livewire\Settings\Workflow;

use App\Models\Department;
use App\Models\DepartmentQueue;
use App\Models\WorkflowSetting;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Component;
use Masmerise\Toaster\Toaster as Notifier;

class Index extends Component
{
    public array $settings = [];
    public array $queues = [];

    public function mount(): void
    {
        Gate::authorize('workflow.manage-settings');
        $this->settings = WorkflowSetting::query()->forCurrentFacility()->pluck('value', 'key')->map(fn ($value) => (bool) ($value[0] ?? $value))->all();
        $this->queues = DepartmentQueue::query()->forCurrentFacility()->get()->mapWithKeys(fn ($queue) => [$queue->id => [
            'queue_prefix' => $queue->queue_prefix,
            'is_active' => $queue->is_active,
            'print_tickets' => $queue->print_tickets,
            'display_screen_enabled' => $queue->display_screen_enabled,
        ]])->all();
    }

    public function save(): void
    {
        Gate::authorize('workflow.manage-settings');
        foreach ($this->settings as $key => $value) {
            WorkflowSetting::query()->updateOrCreate(['facility_id' => currentFacility()?->id, 'key' => $key], ['value' => (bool) $value, 'type' => 'boolean', 'group' => 'workflow', 'updated_by' => auth()->id()]);
        }
        foreach ($this->queues as $id => $data) {
            DepartmentQueue::query()->forCurrentFacility()->whereKey($id)->update([
                'queue_prefix' => str($data['queue_prefix'])->upper()->substr(0, 4),
                'is_active' => (bool) ($data['is_active'] ?? false),
                'print_tickets' => (bool) ($data['print_tickets'] ?? false),
                'display_screen_enabled' => (bool) ($data['display_screen_enabled'] ?? false),
                'updated_by' => auth()->id(),
            ]);
        }
        Notifier::success('Workflow settings updated.');
    }

    public function render(): View
    {
        Department::query()->forCurrentFacility()->where('queue_enabled', true)->whereDoesntHave('departmentQueue')->get()->each(function (Department $department): void {
            $queue = DepartmentQueue::query()->create(['facility_id' => currentFacility()?->id, 'department_id' => $department->id, 'queue_prefix' => str($department->code)->upper()->substr(0, 3), 'is_active' => true, 'created_by' => auth()->id()]);
            $this->queues[$queue->id] = ['queue_prefix' => $queue->queue_prefix, 'is_active' => true, 'print_tickets' => false, 'display_screen_enabled' => false];
        });

        return view('livewire.settings.workflow.index', [
            'workflowSettings' => WorkflowSetting::query()->forCurrentFacility()->orderBy('key')->get(),
            'departmentQueues' => DepartmentQueue::query()->forCurrentFacility()->with('department')->orderBy('queue_prefix')->get(),
        ])->layout('components.layouts.app', ['title' => 'Workflow Settings', 'description' => 'Facility patient movement and queue rules.']);
    }
}
