# ICD-10 catalogue import

The ICD-10 catalogue is global. It is not scoped to a facility. The repository does not contain a production ICD-10 dataset; the system owner must obtain and approve a catalogue whose licensing and clinical version are appropriate for the deployment.

## Accepted CSV format

CSV files must contain a header row and one diagnosis per row. UTF-8 is recommended. `code` and `title` are required; the remaining fields are optional.

| Database field | Accepted headers |
| --- | --- |
| `code` | `code`, `icd_code`, `diagnosis_code` |
| `title` | `title`, `diagnosis`, `diagnosis_name`, `description_short` |
| `description` | `description`, `long_description` |
| `chapter` | `chapter`, `chapter_name` |
| `category` | `category`, `category_name` |

Example structure only (not catalogue data):

```csv
code,title,description,chapter,category
```

Codes are trimmed and normalized to uppercase. Blank rows are ignored. Rows missing code or title and duplicate codes within the source file are skipped and reported. Imports use `updateOrCreate(['code' => $code])`, never delete existing codes or diagnoses, preserve unrelated metadata, and are safe to rerun.

## Deploy permissions

After deploying, seed the new permissions and role mapping:

```bash
php artisan db:seed --class='Database\Seeders\PermissionSeeder' --force
php artisan db:seed --class='Database\Seeders\RolePermissionSeeder' --force
```

Administrators receive `icd10.view`, `icd10.manage`, and `icd10.import`. Doctors and clinical officers continue to use the global lookup without catalogue-management permission.

## Validate with a dry-run

Always dry-run the approved file first:

```bash
php artisan icd10:import /path/to/catalogue.csv --dry-run \
  --source="Approved ICD-10 Catalogue" \
  --source-version="2026"
```

Dry-run validates the complete CSV and reports inserted, updated, unchanged, skipped, and failed counts without changing `icd10_codes`. A dry-run audit entry is intentionally written to `activity_logs`.

## Import

After reviewing the dry-run summary:

```bash
php artisan icd10:import /path/to/catalogue.csv \
  --source="Approved ICD-10 Catalogue" \
  --source-version="2026"
```

The path may be absolute or relative to the project root. Administrators may instead use `/administration/clinical-catalogues/icd10`, which accepts a validated CSV upload and defaults to dry-run.

Artisan reserves `--version` as a global flag, so catalogue version input uses `--source-version`.

## Verify

```bash
php artisan tinker --execute="dump(App\Models\Icd10Code::query()->count(), App\Models\Icd10Code::query()->where('is_active', true)->count());"
```

Review the source, version, row failures, and final counts on the administration page. Search representative codes and descriptions from the approved source before enabling production use.

## Rollback expectations

There is no automatic destructive rollback. Re-importing an earlier approved version updates matching codes but does not remove codes introduced by another import. Before a production import, take a database backup. If rollback is required, restore that backup or explicitly deactivate reviewed catalogue records from the administration page. Diagnosis records are never deleted by this workflow.

`MinimalIcd10Seeder` is development/testing data only and must not be used as a production catalogue.
