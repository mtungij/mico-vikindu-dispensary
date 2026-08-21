<?php

namespace Tests\Feature\Patients;

use App\Models\Facility;
use App\Models\Patient;
use App\Models\PatientPayerProfile;
use App\Models\User;
use App\Services\FacilityContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PatientCardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(FacilityContext::class)->forget();
    }

    public function test_authenticated_authorized_user_can_open_preview_and_print_pages(): void
    {
        [$user, $facility, $patient] = $this->cardContext();

        $this->actingAs($user)->get(route('patients.card', $patient))
            ->assertOk()
            ->assertSee('Patient Card')
            ->assertSee('Back to Patient')
            ->assertSee('Print Patient Card')
            ->assertSee('patient-card-workspace', false)
            ->assertSee('<aside', false)
            ->assertSee('<header class="sticky top-0', false)
            ->assertSee($facility->name)
            ->assertSee($patient->patient_number);

        $this->actingAs($user)->get(route('patients.card.print', $patient))
            ->assertOk()
            ->assertSee('@page { size: 53.98mm 85.60mm; margin: 0; }', false)
            ->assertDontSee('<div class="patient-card-workspace">', false)
            ->assertDontSee('<aside', false)
            ->assertDontSee('<header class="sticky top-0', false)
            ->assertSee($patient->fullName());
    }

    public function test_both_card_routes_require_authentication(): void
    {
        [, , $patient] = $this->cardContext();

        $this->get(route('patients.card', $patient))->assertRedirect(route('login'));
        $this->get(route('patients.card.print', $patient))->assertRedirect(route('login'));
    }

    public function test_user_without_print_permission_is_forbidden_on_both_routes(): void
    {
        [, , $patient] = $this->cardContext();
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('patients.card', $patient))->assertForbidden();
        $this->actingAs($user)->get(route('patients.card.print', $patient))->assertForbidden();
    }

    public function test_cross_facility_patient_is_not_exposed_even_to_super_admin(): void
    {
        [$user] = $this->cardContext();
        $otherFacility = Facility::factory()->create(['setup_completed_at' => now()]);
        $otherPatient = Patient::factory()->create(['facility_id' => $otherFacility->id, 'created_by' => $user->id]);

        $this->actingAs($user)->get(route('patients.card', $otherPatient))->assertNotFound();
        $this->actingAs($user)->get(route('patients.card.print', $otherPatient))->assertNotFound();
    }

    public function test_card_uses_dynamic_branding_demographics_phone_and_payer_type(): void
    {
        [$user, $facility, $patient] = $this->cardContext([
            'first_name' => 'Neema',
            'middle_name' => 'Asha',
            'last_name' => 'Mollel',
            'gender' => 'female',
            'age_years' => 36,
            'date_of_birth' => null,
            'primary_phone' => '0712345678',
        ], ['name' => 'Upendo Community Hospital', 'receipt_header' => 'Compassion in every visit']);
        PatientPayerProfile::query()->create([
            'patient_id' => $patient->id,
            'facility_id' => $facility->id,
            'payer_type' => 'insurance',
            'is_primary' => true,
            'created_by' => $user->id,
        ]);

        $this->actingAs($user)->get(route('patients.card', $patient))
            ->assertOk()
            ->assertSee('Upendo Community Hospital')
            ->assertSee('Compassion in every visit')
            ->assertSee('Neema Asha Mollel')
            ->assertSee($patient->gender->label())
            ->assertSee($patient->ageLabel())
            ->assertSee('0712345678')
            ->assertSee('Insurance');
    }

    public function test_blank_optional_phone_payer_and_slogan_are_omitted(): void
    {
        [$user, , $patient] = $this->cardContext(['primary_phone' => null], ['receipt_header' => null]);

        $this->actingAs($user)->get(route('patients.card', $patient))
            ->assertOk()
            ->assertDontSee('>Phone</dt>', false)
            ->assertDontSee('>Payer type</dt>', false)
            ->assertDontSee('<p class="patient-id-card__slogan">', false);
    }

    public function test_uploaded_patient_photo_and_facility_logo_are_embedded_for_reliable_printing(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('patients/photo.png', 'patient-photo-bytes');
        Storage::disk('public')->put('branding/logo.png', 'facility-logo-bytes');
        [$user, , $patient] = $this->cardContext(
            ['passport_photo_path' => 'patients/photo.png'],
            ['logo_path' => 'branding/logo.png'],
        );

        $this->actingAs($user)->get(route('patients.card.print', $patient))
            ->assertOk()
            ->assertSee('data-testid="patient-card-photo"', false)
            ->assertSee('data:image/png;base64,'.base64_encode('patient-photo-bytes'), false)
            ->assertSee('data:image/png;base64,'.base64_encode('facility-logo-bytes'), false)
            ->assertDontSee('data-testid="patient-card-avatar"', false);
    }

    public function test_missing_images_render_safe_avatar_and_facility_icon_fallbacks(): void
    {
        Storage::fake('public');
        [$user, , $patient] = $this->cardContext([
            'first_name' => 'Amina',
            'last_name' => 'Juma',
            'passport_photo_path' => 'patients/missing.png',
        ], ['logo_path' => 'branding/missing.png']);

        $this->actingAs($user)->get(route('patients.card', $patient))
            ->assertOk()
            ->assertSee('data-testid="patient-card-avatar"', false)
            ->assertSee('AJ')
            ->assertSee('patient-id-card__logo-fallback', false);
    }

    public function test_qr_is_real_svg_and_contains_only_the_authenticated_card_url(): void
    {
        [$user, , $patient] = $this->cardContext([
            'primary_phone' => '0799000111',
            'nida_number' => '19900101-12345-00001-11',
            'known_allergies' => 'Penicillin',
        ]);
        $response = $this->actingAs($user)->get(route('patients.card', $patient))->assertOk();
        $html = $response->getContent();

        $this->assertStringNotContainsString('QR Placeholder', $html);
        $this->assertStringContainsString('data:image/svg+xml;base64,', $html);
        $this->assertMatchesRegularExpression('/data-qr-payload="([^"]+)"/', $html);
        preg_match('/data-qr-payload="([^"]+)"/', $html, $match);
        $payload = html_entity_decode($match[1], ENT_QUOTES);

        $this->assertSame(route('patients.card', $patient, true), $payload);
        $this->assertStringNotContainsString($patient->primary_phone, $payload);
        $this->assertStringNotContainsString($patient->nida_number, $payload);
        $this->assertStringNotContainsString($patient->known_allergies, $payload);
        $this->assertStringNotContainsString($patient->fullName(), $payload);
    }

    public function test_preview_and_print_requests_do_not_mutate_patient_or_clinical_financial_records(): void
    {
        [$user, , $patient] = $this->cardContext();
        $before = $this->readOnlyFingerprint($patient);

        $this->actingAs($user)->get(route('patients.card', $patient))->assertOk();
        $this->actingAs($user)->get(route('patients.card.print', $patient))->assertOk();

        $this->assertSame($before, $this->readOnlyFingerprint($patient));
    }

    /** @param array<string, mixed> $patientOverrides @param array<string, mixed> $facilityOverrides */
    private function cardContext(array $patientOverrides = [], array $facilityOverrides = []): array
    {
        $user = User::factory()->superAdmin()->create();
        $facility = Facility::factory()->create([
            'name' => 'Mwangaza Health Centre',
            'setup_completed_at' => now(),
            'created_by' => $user->id,
            ...$facilityOverrides,
        ]);
        app(FacilityContext::class)->forget();
        $patient = Patient::factory()->create([
            'facility_id' => $facility->id,
            'patient_number' => 'PAT-2026-000123',
            'first_name' => 'Amani',
            'last_name' => 'Salum',
            'date_of_birth' => now()->subYears(29)->startOfDay(),
            'created_by' => $user->id,
            ...$patientOverrides,
        ]);

        return [$user, $facility, $patient];
    }

    /** @return array<string, mixed> */
    private function readOnlyFingerprint(Patient $patient): array
    {
        $patient->refresh();

        return [
            'patient_updated_at' => $patient->updated_at?->toISOString(),
            'visits' => DB::table('visits')->where('patient_id', $patient->id)->count(),
            'invoices' => DB::table('invoices')->where('patient_id', $patient->id)->count(),
            'prescriptions' => DB::table('prescriptions')->where('patient_id', $patient->id)->count(),
            'dispensings' => DB::table('dispensings')->where('patient_id', $patient->id)->count(),
            'stock_movements' => DB::table('stock_movements')->count(),
        ];
    }
}
