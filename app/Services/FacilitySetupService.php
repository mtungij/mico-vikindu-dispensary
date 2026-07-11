<?php

namespace App\Services;

use App\Events\FacilitySetupCompleted;
use App\Models\Facility;
use App\Models\FacilitySetting;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class FacilitySetupService
{
    public function __construct(private readonly FacilityContext $context) {}

    public function getCurrentFacility(): ?Facility
    {
        return $this->context->current();
    }

    public function hasFacility(): bool
    {
        return $this->getCurrentFacility() !== null;
    }

    public function isSetupCompleted(): bool
    {
        return $this->getCurrentFacility()?->isSetupCompleted() ?? false;
    }

    /**
     * @return array{completed_steps:int, total_steps:int, percentage:int}
     */
    public function getSetupProgress(?Facility $facility = null): array
    {
        $facility ??= $this->getCurrentFacility();
        $completed = $facility ? $this->calculateCompletedSteps($facility) : 0;

        return [
            'completed_steps' => $completed,
            'total_steps' => 5,
            'percentage' => min(100, $completed * 20),
        ];
    }

    public function calculateCompletedSteps(Facility $facility): int
    {
        $steps = 0;
        $steps += $facility->name && $facility->facility_type && $facility->ownership_type && $facility->phone_primary ? 1 : 0;
        $steps += $facility->region && $facility->district && $facility->ward && $facility->physical_address ? 1 : 0;
        $steps += $facility->operating_license_number || $facility->registration_number || $facility->tin_number || $this->getSetting($facility, 'accepts_insurance') !== null ? 1 : 0;
        $steps += $facility->logo_path || $facility->documents()->exists() ? 1 : 0;
        $steps += $facility->currency && $facility->timezone && $facility->date_format && $facility->time_format ? 1 : 0;

        return $steps;
    }

    public function markSetupCompleted(Facility $facility, User $user): Facility
    {
        return DB::transaction(function () use ($facility, $user): Facility {
            $this->ensureRequiredSettingsExist($facility);
            $facility->forceFill([
                'setup_current_step' => 6,
                'setup_completed_at' => now(),
                'updated_by' => $user->id,
            ])->save();
            ActivityLog::query()->create([
                'user_id' => $user->id,
                'event' => 'facility.setup_completed',
                'subject_type' => Facility::class,
                'subject_id' => $facility->id,
                'new_values' => ['setup_completed_at' => $facility->setup_completed_at?->toISOString()],
                'ip_address' => request()?->ip(),
                'user_agent' => request()?->userAgent(),
            ]);
            $this->context->forget();
            event(new FacilitySetupCompleted($facility, $user));

            return $facility->refresh();
        });
    }

    public function ensureRequiredSettingsExist(Facility $facility): void
    {
        foreach (config('facility.settings', []) as $key => $setting) {
            $this->saveSetting($facility, $key, $setting['value'], $setting['type'], $setting['group']);
        }
    }

    public function saveSetting(Facility $facility, string $key, mixed $value, string $type = 'string', ?string $group = null, bool $isPublic = false): FacilitySetting
    {
        $stored = is_bool($value) ? ($value ? '1' : '0') : (string) $value;

        return FacilitySetting::query()->updateOrCreate(
            ['facility_id' => $facility->id, 'key' => $key],
            ['value' => $stored, 'type' => $type, 'group' => $group, 'is_public' => $isPublic],
        );
    }

    public function getSetting(Facility $facility, string $key, mixed $default = null): mixed
    {
        $setting = $facility->settings->firstWhere('key', $key)
            ?? $facility->settings()->where('key', $key)->first();

        if ($setting === null) {
            return $default;
        }

        return match ($setting->type) {
            'boolean' => filter_var($setting->value, FILTER_VALIDATE_BOOLEAN),
            'integer' => (int) $setting->value,
            default => $setting->value,
        };
    }

    public function deleteOldFileSafely(?string $path, string $disk = 'public'): void
    {
        if ($path && Storage::disk($disk)->exists($path)) {
            Storage::disk($disk)->delete($path);
        }
    }

    /**
     * @return array{blocking:array<int,string>,warnings:array<int,string>}
     */
    public function validateSetupReadiness(Facility $facility, User $user): array
    {
        $blocking = [];
        $warnings = [];

        foreach ([
            'Facility name present' => $facility->name,
            'Facility type selected' => $facility->facility_type,
            'Ownership selected' => $facility->ownership_type,
            'Primary phone present' => $facility->phone_primary,
            'Region and district present' => $facility->region && $facility->district,
            'Physical address present' => $facility->physical_address,
            'Currency configured' => $facility->currency,
            'Timezone configured' => $facility->timezone,
            'Super Admin confirmed' => $user->is_super_admin,
        ] as $label => $passes) {
            if (! $passes) {
                $blocking[] = $label;
            }
        }

        foreach ([
            'Logo haijapakiwa' => $facility->logo_path === null,
            'NHIF haijawezeshwa' => ! $this->getSetting($facility, 'nhif_enabled', false),
            'Operating license expiry missing' => $facility->operating_license_expiry_date === null,
            'Official stamp missing' => $facility->official_stamp_path === null,
            'Email missing' => $facility->email === null,
        ] as $label => $applies) {
            if ($applies) {
                $warnings[] = $label;
            }
        }

        return ['blocking' => $blocking, 'warnings' => $warnings];
    }

    public function generateUniqueCode(string $name): string
    {
        $initials = collect(preg_split('/\s+/', trim($name)))
            ->filter()
            ->map(fn (string $word): string => Str::upper(Str::substr($word, 0, 1)))
            ->join('');

        $base = Str::substr($initials ?: Str::slug($name, ''), 0, 10) ?: 'FAC';
        $code = $base;
        $suffix = 1;

        while (Facility::query()->where('code', $code)->exists()) {
            $code = $base.$suffix++;
        }

        return $code;
    }
}
