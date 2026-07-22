<?php

namespace Tests\Feature\ClinicalEncounters;

use App\Livewire\Administration\ClinicalCatalogues\Icd10Index as Icd10CatalogueIndex;
use App\Livewire\Clinical\Icd10Search;
use App\Models\ActivityLog;
use App\Models\Facility;
use App\Models\Icd10Code;
use App\Models\Role;
use App\Models\StaffProfile;
use App\Models\User;
use App\Services\Icd10ImportService;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\MinimalIcd10Seeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;
use Livewire\Livewire;
use Tests\TestCase;

class Icd10CatalogueManagementTest extends TestCase
{
    use RefreshDatabase;

    private array $temporaryFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->temporaryFiles as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }

        parent::tearDown();
    }

    public function test_import_inserts_normalized_codes_and_records_source_metadata_and_audit(): void
    {
        $result = $this->import(" code , title ,description,chapter,category\n a01 , Typhoid fever , Long text ,Infections,Enteric\n", 'Approved Ministry Catalogue', '2026');

        $this->assertSame(1, $result['inserted']);
        $this->assertSame(0, $result['failed']);
        $this->assertDatabaseHas('icd10_codes', ['code' => 'A01', 'title' => 'Typhoid fever', 'chapter' => 'Infections']);
        $code = Icd10Code::query()->where('code', 'A01')->firstOrFail();
        $this->assertSame('Approved Ministry Catalogue', $code->metadata['import_source']);
        $this->assertSame('2026', $code->metadata['source_version']);
        $this->assertSame(2, $code->metadata['source_row']);
        $this->assertNotEmpty($code->metadata['imported_at']);
        $this->assertDatabaseHas('activity_logs', ['event' => 'icd10.catalogue_imported']);
        $this->assertSame('system/CLI', ActivityLog::query()->latest()->firstOrFail()->new_values['actor']);
    }

    public function test_reimport_updates_without_duplication_then_reports_unchanged(): void
    {
        $path = $this->csv("code,title\na01,Original title\n");
        $service = app(Icd10ImportService::class);
        $service->import($path, 'Approved source', '1');
        file_put_contents($path, "code,title\na01,Updated title\n");

        $updated = $service->import($path, 'Approved source', '1');
        $unchanged = $service->import($path, 'Approved source', '1');

        $this->assertSame(1, $updated['updated']);
        $this->assertSame(1, $unchanged['unchanged']);
        $this->assertSame(1, Icd10Code::query()->where('code', 'A01')->count());
        $this->assertSame('Updated title', Icd10Code::query()->where('code', 'A01')->value('title'));
    }

    public function test_dry_run_validates_and_audits_without_changing_catalogue(): void
    {
        $result = $this->import("code,title\nb54,Unspecified malaria\n", dryRun: true);

        $this->assertTrue($result['dry_run']);
        $this->assertSame(1, $result['inserted']);
        $this->assertDatabaseCount('icd10_codes', 0);
        $this->assertTrue(ActivityLog::query()->latest()->firstOrFail()->new_values['dry_run']);
    }

    public function test_invalid_headers_fail_clearly(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing required CSV header for code');

        app(Icd10ImportService::class)->import($this->csv("unknown,title\nA01,Title\n"));
    }

    public function test_missing_fields_and_duplicate_source_codes_are_skipped_and_reported(): void
    {
        $result = $this->import("code,title\n,Missing code\nA01,Valid title\na01,Duplicate title\nA02,\n");

        $this->assertSame(4, $result['total']);
        $this->assertSame(1, $result['inserted']);
        $this->assertSame(3, $result['skipped']);
        $this->assertCount(3, $result['failures']);
        $this->assertDatabaseCount('icd10_codes', 1);
    }

    public function test_common_header_aliases_are_mapped(): void
    {
        $this->import("diagnosis_code,diagnosis_name,long_description,chapter_name,category_name\ne11,Type 2 diabetes,Long diabetes description,Endocrine,Diabetes\n");

        $this->assertDatabaseHas('icd10_codes', [
            'code' => 'E11',
            'title' => 'Type 2 diabetes',
            'description' => 'Long diabetes description',
            'chapter' => 'Endocrine',
            'category' => 'Diabetes',
        ]);
    }

    public function test_import_preserves_unrelated_existing_metadata(): void
    {
        Icd10Code::factory()->create(['code' => 'I10', 'title' => 'Old title', 'metadata' => ['local_note' => 'Keep me']]);

        $this->import("icd_code,description_short\ni10,Essential hypertension\n", 'Approved source', '2');

        $metadata = Icd10Code::query()->where('code', 'I10')->firstOrFail()->metadata;
        $this->assertSame('Keep me', $metadata['local_note']);
        $this->assertSame('Approved source', $metadata['import_source']);
    }

    public function test_large_csv_is_processed_across_streaming_chunks(): void
    {
        $path = $this->csv("code,title\n");
        $handle = fopen($path, 'a');
        for ($index = 0; $index < 1001; $index++) {
            fputcsv($handle, ['X'.str_pad((string) $index, 4, '0', STR_PAD_LEFT), "Diagnosis {$index}"]);
        }
        fclose($handle);

        $result = app(Icd10ImportService::class)->import($path, 'Large approved test source');

        $this->assertSame(1001, $result['total']);
        $this->assertSame(1001, $result['inserted']);
        $this->assertDatabaseCount('icd10_codes', 1001);
    }

    public function test_admin_page_is_permission_protected_and_super_admin_can_view_it(): void
    {
        $admin = User::factory()->superAdmin()->create();
        Facility::factory()->create(['created_by' => $admin->id, 'updated_by' => $admin->id]);
        $unauthorized = User::factory()->create();
        StaffProfile::factory()->create(['facility_id' => currentFacility()->id, 'user_id' => $unauthorized->id]);

        $this->actingAs($unauthorized)->get(route('administration.clinical-catalogues.icd10'))->assertForbidden();
        $this->actingAs($admin)->get(route('administration.clinical-catalogues.icd10'))->assertOk()->assertSee('ICD-10 Catalogue Management');
    }

    public function test_authorized_admin_can_upload_and_import_an_approved_csv(): void
    {
        $admin = User::factory()->superAdmin()->create();

        Livewire::actingAs($admin)
            ->test(Icd10CatalogueIndex::class)
            ->set('csvFile', UploadedFile::fake()->createWithContent('approved-icd10.csv', "code,title\nB54,Unspecified malaria\n"))
            ->set('source', 'Approved test catalogue')
            ->set('sourceVersion', '2026')
            ->set('dryRun', false)
            ->call('import')
            ->assertHasNoErrors()
            ->assertSet('importResult.inserted', 1);

        $this->assertDatabaseHas('icd10_codes', ['code' => 'B54', 'title' => 'Unspecified malaria']);
        $this->assertSame($admin->id, ActivityLog::query()->where('event', 'icd10.catalogue_imported')->latest()->value('user_id'));
    }

    public function test_user_without_import_permission_cannot_authorize_import_or_management(): void
    {
        $user = User::factory()->create();
        $this->seed(PermissionSeeder::class);
        $user->givePermissionTo('icd10.view');
        $this->actingAs($user);

        $this->assertFalse(Gate::allows('icd10.import'));
        $this->assertFalse(Gate::allows('icd10.manage'));
        $this->expectException(AuthorizationException::class);
        Gate::authorize('icd10.import');
    }

    public function test_icd10_permissions_are_seeded_only_to_administrator_roles(): void
    {
        $owner = User::factory()->superAdmin()->create();
        Facility::factory()->create(['created_by' => $owner->id, 'updated_by' => $owner->id]);
        $this->seed([PermissionSeeder::class, RoleSeeder::class, RolePermissionSeeder::class]);

        $administrator = Role::query()->where('name', 'administrator')->firstOrFail();
        $doctor = Role::query()->where('name', 'doctor')->firstOrFail();

        foreach (['icd10.view', 'icd10.manage', 'icd10.import'] as $permission) {
            $this->assertTrue($administrator->hasPermissionTo($permission));
            $this->assertFalse($doctor->hasPermissionTo($permission));
        }
    }

    public function test_doctor_lookup_remains_global_without_icd10_management_permission(): void
    {
        $doctor = User::factory()->create();
        Icd10Code::factory()->create(['code' => 'B54', 'title' => 'Unspecified malaria', 'is_active' => true]);

        Livewire::actingAs($doctor)->test(Icd10Search::class)->set('query', 'malaria')->assertSee('B54');
    }

    public function test_development_sample_warning_displays_only_for_sample_catalogue(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->seed(MinimalIcd10Seeder::class);

        Livewire::actingAs($admin)
            ->test(Icd10Search::class)
            ->assertSee('The ICD-10 catalogue contains development sample records only. Import an approved full catalogue before production use.');

        Icd10Code::factory()->create(['code' => 'Z99', 'title' => 'Approved catalogue record', 'chapter' => 'Approved']);

        Livewire::actingAs($admin)
            ->test(Icd10Search::class)
            ->assertDontSee('The ICD-10 catalogue contains development sample records only.');
    }

    public function test_command_reports_invalid_headers_and_supports_dry_run_options(): void
    {
        $invalid = Artisan::call('icd10:import', ['file' => $this->csv("bad,title\nA01,Title\n")]);
        $this->assertSame(1, $invalid);
        $this->assertStringContainsString('Missing required CSV header for code', Artisan::output());

        $valid = Artisan::call('icd10:import', [
            'file' => $this->csv("code,title\na01,Title\n"),
            '--dry-run' => true,
            '--source' => 'Approved source',
            '--source-version' => '2026',
        ]);
        $this->assertSame(0, $valid);
        $this->assertStringContainsString('Total rows processed', Artisan::output());
        $this->assertDatabaseCount('icd10_codes', 0);
    }

    public function test_production_environment_does_not_seed_minimal_icd10_catalogue(): void
    {
        $originalEnvironment = app()->environment();
        app()->detectEnvironment(fn (): string => 'production');

        try {
            $this->assertFalse((new DatabaseSeeder)->shouldSeedDevelopmentData());
        } finally {
            app()->detectEnvironment(fn (): string => $originalEnvironment);
        }
    }

    private function import(string $contents, ?string $source = null, ?string $version = null, bool $dryRun = false): array
    {
        return app(Icd10ImportService::class)->import($this->csv($contents), $source, $version, $dryRun);
    }

    private function csv(string $contents): string
    {
        $path = tempnam(sys_get_temp_dir(), 'icd10-');
        file_put_contents($path, $contents);
        $this->temporaryFiles[] = $path;

        return $path;
    }
}
