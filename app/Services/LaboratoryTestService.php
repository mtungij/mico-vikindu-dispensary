<?php

namespace App\Services;

use App\Enums\ServiceType;
use App\Models\ActivityLog;
use App\Models\LaboratoryTest;
use App\Models\LaboratoryTestParameter;
use App\Models\LaboratoryTestPanel;
use App\Models\Service;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LaboratoryTestService
{
    public function createTest(array $data, $actor): LaboratoryTest
    {
        return DB::transaction(function () use ($data, $actor) {
            $this->validateService($data['service_id'] ?? null);
            $test = LaboratoryTest::query()->create([...$data, 'facility_id' => currentFacility()->id, 'code' => str($data['code'])->upper(), 'created_by' => $actor->id]);
            ActivityLog::query()->create(['user_id' => $actor->id, 'event' => 'laboratory_test_created', 'subject_type' => $test::class, 'subject_id' => $test->id]);
            return $test;
        });
    }

    public function addParameter(LaboratoryTest $test, array $data, $actor): LaboratoryTestParameter
    {
        $parameter = $test->parameters()->create([...$data, 'facility_id' => $test->facility_id, 'code' => str($data['code'])->upper(), 'created_by' => $actor->id]);
        ActivityLog::query()->create(['user_id' => $actor->id, 'event' => 'laboratory_parameter_added', 'subject_type' => $parameter::class, 'subject_id' => $parameter->id]);
        return $parameter;
    }

    public function addPanelChild(LaboratoryTest $parent, LaboratoryTest $child): LaboratoryTestPanel
    {
        if (! $parent->is_panel || $parent->id === $child->id || $parent->facility_id !== $child->facility_id || $this->wouldCreateCircularPanel($parent, $child)) {
            throw ValidationException::withMessages(['child_laboratory_test_id' => 'Panel relationship si sahihi.']);
        }
        return LaboratoryTestPanel::query()->firstOrCreate(['laboratory_test_id' => $parent->id, 'child_laboratory_test_id' => $child->id], ['facility_id' => $parent->facility_id]);
    }

    private function validateService(?int $serviceId): void
    {
        if (! $serviceId) {
            return;
        }
        $service = Service::query()->where('facility_id', currentFacility()->id)->findOrFail($serviceId);
        if ($service->service_type !== ServiceType::LaboratoryTest) {
            throw ValidationException::withMessages(['service_id' => 'Service lazima iwe laboratory_test.']);
        }
    }

    private function wouldCreateCircularPanel(LaboratoryTest $parent, LaboratoryTest $child): bool
    {
        return LaboratoryTestPanel::query()->where('laboratory_test_id', $child->id)->where('child_laboratory_test_id', $parent->id)->exists();
    }
}
