<?php

namespace App\Services;

use App\Enums\PayerType;
use App\Enums\ServiceType;
use App\Models\ActivityLog;
use App\Models\Department;
use App\Models\Facility;
use App\Models\FacilitySetting;
use App\Models\Invoice;
use App\Models\Patient;
use App\Models\PatientPayerProfile;
use App\Models\Service;
use App\Models\ServicePrice;
use App\Models\Visit;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReceptionChargeService
{
    public function __construct(
        private readonly FacilitySetupService $settings,
        private readonly ServicePricingService $pricing,
        private readonly InvoiceService $invoices,
    ) {}

    public function resolveRegistrationService(Facility $facility, bool $isNewPatient): ?Service
    {
        if (! $this->bool($facility, 'auto_add_registration_fee', true)) {
            return null;
        }

        $chargeKey = $isNewPatient ? 'charge_new_patient_registration' : 'charge_returning_patient_registration';
        if (! $this->bool($facility, $chargeKey, $isNewPatient)) {
            return null;
        }

        $settingKey = $isNewPatient ? 'new_patient_registration_service_id' : 'returning_patient_registration_service_id';
        $configuredId = $this->value($facility, $settingKey);
        $service = $configuredId ? Service::query()->where('facility_id', $facility->id)->find((int) $configuredId) : null;

        $service ??= Service::query()
            ->where('facility_id', $facility->id)
            ->where('service_type', ServiceType::Registration->value)
            ->whereIn('code', $isNewPatient ? ['NEW-REG', 'NEWREG'] : ['RETURN-REG', 'RETREG'])
            ->where('is_active', true)
            ->first();

        $this->validateRegistrationService($service, $isNewPatient);

        return $service;
    }

    public function resolveConsultationService(Facility $facility, Department $destination, ?int $consultationServiceId): ?Service
    {
        if (! $this->bool($facility, 'auto_add_consultation_fee', true) || ! $this->bool($facility, 'require_consultation_service', true) || ! $destination->requires_consultation) {
            return null;
        }

        if (! $consultationServiceId) {
            throw ValidationException::withMessages(['consultation_service_id' => 'Chagua consultation service kwa destination hii.']);
        }

        $service = Service::query()
            ->where('facility_id', $facility->id)
            ->where('department_id', $destination->id)
            ->where('service_type', ServiceType::Consultation->value)
            ->where('is_active', true)
            ->find($consultationServiceId);

        if (! $service) {
            throw ValidationException::withMessages(['consultation_service_id' => 'Consultation service si active, si ya destination iliyochaguliwa, au si ya facility hii.']);
        }

        return $service;
    }

    public function resolveRegistrationPrice(Service $service, PayerType $payerType, ?int $insuranceProviderId = null, ?int $corporateAccountId = null): ?ServicePrice
    {
        return $this->requiredPrice($service, $payerType, $insuranceProviderId, $corporateAccountId);
    }

    public function resolveConsultationPrice(Service $service, PayerType $payerType, ?int $insuranceProviderId = null, ?int $corporateAccountId = null): ?ServicePrice
    {
        return $this->requiredPrice($service, $payerType, $insuranceProviderId, $corporateAccountId);
    }

    public function buildChargePreview(Facility $facility, bool $isNewPatient, ?int $destinationDepartmentId, ?int $consultationServiceId, array $payerData): array
    {
        $payerType = PayerType::from($payerData['payer_type'] ?? 'cash');
        $warnings = [];
        $blocking = [];
        $lines = [];
        $destination = null;

        if ($destinationDepartmentId) {
            try {
                $destination = $this->destination($facility, $destinationDepartmentId);
            } catch (ValidationException $exception) {
                $blocking = array_merge($blocking, $this->messages($exception));
            }
        }

        try {
            $registration = $this->resolveRegistrationService($facility, $isNewPatient);
            if ($registration) {
                $price = $this->resolveRegistrationPrice($registration, $payerType, $payerData['insurance_provider_id'] ?? null, $payerData['corporate_account_id'] ?? null);
                $lines[] = $this->line('registration', $registration, $price, $payerType, ['charge_source' => 'patient_registration']);
            }
        } catch (ValidationException $exception) {
            $blocking = array_merge($blocking, $this->messages($exception));
        }

        if ($destination) {
            try {
                $consultation = $this->resolveConsultationService($facility, $destination, $consultationServiceId);
                if ($consultation) {
                    $price = $this->resolveConsultationPrice($consultation, $payerType, $payerData['insurance_provider_id'] ?? null, $payerData['corporate_account_id'] ?? null);
                    $lines[] = $this->line('consultation', $consultation, $price, $payerType, ['destination_department_id' => $destination->id]);
                } elseif (! $destination->requires_consultation) {
                    $warnings[] = 'Destination hii haihitaji consultation fee.';
                }
            } catch (ValidationException $exception) {
                $blocking = array_merge($blocking, $this->messages($exception));
            }
        }

        $totals = $this->totals($lines);

        return [
            'registration' => collect($lines)->firstWhere('item_type', ServiceType::Registration->value),
            'consultation' => collect($lines)->firstWhere('item_type', ServiceType::Consultation->value),
            'lines' => $lines,
            'total' => $totals['total'],
            'patient_amount' => $totals['patient_amount'],
            'insurance_amount' => $totals['insurance_amount'],
            'corporate_amount' => $totals['corporate_amount'],
            'payer_amount' => $totals['payer_amount'],
            'warnings' => $warnings,
            'blocking_errors' => $blocking,
            'next_step' => $this->nextStepLabel($payerType, $totals['patient_amount'], (bool) ($payerData['require_payment_before_service'] ?? true), $destination),
        ];
    }

    public function validateChargeConfiguration(Facility $facility, bool $isNewPatient, Department $destination, ?Service $consultationService, PayerType $payerType, ?int $insuranceProviderId = null, ?int $corporateAccountId = null): array
    {
        $registration = $this->resolveRegistrationService($facility, $isNewPatient);
        if ($registration) {
            $this->resolveRegistrationPrice($registration, $payerType, $insuranceProviderId, $corporateAccountId);
        }

        if ($destination->requires_consultation) {
            if (! $consultationService) {
                throw ValidationException::withMessages(['consultation_service_id' => 'Chagua consultation service kwa destination hii.']);
            }
            $this->resolveConsultationPrice($consultationService, $payerType, $insuranceProviderId, $corporateAccountId);
        }

        return [$registration, $consultationService];
    }

    public function createInitialInvoiceItems(Invoice $invoice, ?Service $registrationService, ?Service $consultationService, bool $isNewPatient, ?Department $destination, $actor): Invoice
    {
        if ($registrationService) {
            $this->createLine($invoice, $registrationService, [
                'auto_added' => true,
                'charge_source' => 'patient_registration',
                'patient_registration_type' => $isNewPatient ? 'new' : 'returning',
            ], $actor, 'registration_charge_auto_added');
        }

        if ($consultationService) {
            $this->createLine($invoice, $consultationService, [
                'auto_added' => true,
                'charge_source' => 'reception_consultation',
                'destination_department_id' => $destination?->id,
            ], $actor, 'consultation_charge_auto_added');
        }

        return $this->invoices->calculateTotals($invoice);
    }

    public function calculatePayerSplit(PayerType $payerType, float $amount): array
    {
        return $this->invoices->resolvePayerAmounts($payerType, $amount);
    }

    public function requestPatientCardReplacement(Patient $patient, array $data, $actor): Invoice
    {
        $facility = $patient->facility;
        $payerProfile = $patient->primaryPayerProfile;
        $payerType = $payerProfile?->payer_type ?? PayerType::Cash;
        $service = $this->resolvePatientCardReplacementService($facility);

        return DB::transaction(function () use ($patient, $payerProfile, $payerType, $service, $data, $actor): Invoice {
            $invoice = $this->invoices->createPatientInvoice($patient, $payerType, $payerProfile, $actor, 'Patient card replacement');
            $this->createLine($invoice, $service, [
                'auto_added' => false,
                'charge_source' => 'patient_card_replacement',
                'reason' => $data['reason'],
                'details' => $data['details'] ?? null,
            ], $actor, 'patient_card_replacement_charge_added', (float) ($data['quantity'] ?? 1));

            ActivityLog::query()->create([
                'user_id' => $actor->id,
                'event' => 'patient_card_replacement_requested',
                'subject_type' => Patient::class,
                'subject_id' => $patient->id,
                'new_values' => ['invoice_id' => $invoice->id, 'reason' => $data['reason']],
                'ip_address' => request()?->ip(),
                'user_agent' => request()?->userAgent(),
            ]);

            return $this->invoices->calculateTotals($invoice);
        });
    }

    public function destination(Facility $facility, int $departmentId): Department
    {
        $department = Department::query()
            ->where('facility_id', $facility->id)
            ->where('is_active', true)
            ->where('can_receive_patients', true)
            ->find($departmentId);

        if (! $department) {
            throw ValidationException::withMessages(['destination_department_id' => 'Destination si active, si ya facility hii, au hairuhusiwi kupokea wagonjwa.']);
        }

        return $department;
    }

    private function createLine(Invoice $invoice, Service $service, array $metadata, $actor, string $auditEvent, float $quantity = 1): void
    {
        if ($invoice->items()
            ->where('service_id', $service->id)
            ->where('item_type', $service->service_type->value)
            ->exists()) {
            return;
        }

        $price = $this->requiredPrice($service, $invoice->payer_type, $invoice->patientPayerProfile?->insurance_provider_id, $invoice->patientPayerProfile?->corporate_account_id);
        $total = (float) $price->amount * $quantity;
        $split = $this->calculatePayerSplit($invoice->payer_type, $total);

        $invoice->items()->create([
            'service_id' => $service->id,
            'item_type' => $service->service_type->value,
            'description' => $service->name,
            'quantity' => $quantity,
            'unit_price' => $price->amount,
            'total_amount' => $total,
            'payer_amount' => $split['payer_amount'],
            'insurance_amount' => $split['insurance_amount'],
            'patient_amount' => $split['patient_amount'],
            'status' => $invoice->payer_type === PayerType::Cash ? 'pending' : 'covered',
            'metadata' => array_merge($metadata, ['service_code' => $service->code]),
            'created_by' => $actor->id,
        ]);

        ActivityLog::query()->create([
            'user_id' => $actor->id,
            'event' => $auditEvent,
            'subject_type' => Invoice::class,
            'subject_id' => $invoice->id,
            'new_values' => ['service_id' => $service->id, 'item_type' => $service->service_type->value, 'amount' => $total],
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
        ]);
    }

    private function requiredPrice(Service $service, PayerType $payerType, ?int $insuranceProviderId = null, ?int $corporateAccountId = null): ServicePrice
    {
        $price = $this->pricing->getCurrentPrice($service, $payerType, $insuranceProviderId, $corporateAccountId);
        if ($service->requires_payment && ! $price) {
            ActivityLog::query()->create([
                'user_id' => auth()->id(),
                'event' => 'missing_service_price_blocked_registration',
                'subject_type' => Service::class,
                'subject_id' => $service->id,
                'new_values' => ['payer_type' => $payerType->value],
                'ip_address' => request()?->ip(),
                'user_agent' => request()?->userAgent(),
            ]);

            throw ValidationException::withMessages(['service_price' => "Huduma ya {$service->name} bado haijawekewa bei ya {$payerType->label()}."]);
        }

        return $price ?? new ServicePrice(['amount' => 0, 'currency' => 'TZS']);
    }

    private function line(string $kind, Service $service, ServicePrice $price, PayerType $payerType, array $metadata): array
    {
        $amount = (float) $price->amount;
        $split = $this->calculatePayerSplit($payerType, $amount);

        return [
            'kind' => $kind,
            'item_type' => $service->service_type->value,
            'service_id' => $service->id,
            'service_name' => $service->name,
            'amount' => $amount,
            'patient_amount' => $split['patient_amount'],
            'insurance_amount' => $split['insurance_amount'],
            'corporate_amount' => $payerType === PayerType::Corporate ? $split['payer_amount'] : 0,
            'payer_amount' => $split['payer_amount'],
            'metadata' => $metadata,
        ];
    }

    private function totals(array $lines): array
    {
        return [
            'total' => array_sum(array_column($lines, 'amount')),
            'patient_amount' => array_sum(array_column($lines, 'patient_amount')),
            'insurance_amount' => array_sum(array_column($lines, 'insurance_amount')),
            'corporate_amount' => array_sum(array_column($lines, 'corporate_amount')),
            'payer_amount' => array_sum(array_column($lines, 'payer_amount')),
        ];
    }

    private function resolvePatientCardReplacementService(Facility $facility): Service
    {
        $configuredId = $this->value($facility, 'patient_card_replacement_service_id');
        $service = $configuredId ? Service::query()->where('facility_id', $facility->id)->find((int) $configuredId) : null;
        $service ??= Service::query()->where('facility_id', $facility->id)->whereIn('code', ['CARD-REPLACE', 'CARDREP'])->where('is_active', true)->first();

        if (! $service || ! in_array($service->service_type, [ServiceType::AdministrativeService, ServiceType::Registration], true) || ! $service->is_active) {
            throw ValidationException::withMessages(['patient_card_replacement_service_id' => 'Huduma ya Patient Card Replacement haijasanidiwa vizuri.']);
        }

        return $service;
    }

    private function validateRegistrationService(?Service $service, bool $isNewPatient): void
    {
        if (! $service || $service->service_type !== ServiceType::Registration || ! $service->is_active) {
            $label = $isNewPatient ? 'New Patient Registration' : 'Returning Patient Registration';
            throw ValidationException::withMessages(['registration_service_id' => "Huduma ya {$label} haijasanidiwa vizuri."]);
        }
    }

    private function bool(Facility $facility, string $key, bool $default): bool
    {
        return (bool) $this->settings->getSetting($facility->loadMissing('settings'), $key, $default);
    }

    private function value(Facility $facility, string $key): mixed
    {
        $setting = FacilitySetting::query()->where('facility_id', $facility->id)->where('key', $key)->first();

        return filled($setting?->value) ? $setting->value : null;
    }

    private function messages(ValidationException $exception): array
    {
        return collect($exception->errors())->flatten()->values()->all();
    }

    private function nextStepLabel(PayerType $payerType, float $patientAmount, bool $paymentFirst, ?Department $destination): string
    {
        if ($paymentFirst && $patientAmount > 0) {
            return 'Awaiting Payment';
        }

        if ($destination?->requires_triage) {
            return 'Triage';
        }

        return 'Destination Queue';
    }
}
