# Laboratory doctor-review workflow repair

## Cause

The inspected implementation already checked released, unreviewed laboratory results, but VisitClosureService could select AwaitingDoctorReview without creating an OPD queue. Without a queue it retained the previous current department. Queue creation was confined to the result-release path, whose already-released retry returned immediately. A queue/status-derived review blocker also kept workflow state coupled to its own output.

ClinicalEncounterService used the visit's current department for encounter creation and duplicate detection. It selected a parent independently, so an OPD parent could produce a Pharmacy follow-up when the visit context was stale. Completion evaluated closure before marking results reviewed and then evaluated again.

These paths explain how the supplied inconsistent state persists and creates the wrong follow-up. The supplied production snapshot does not establish which earlier request originally wrote the Pharmacy department.

## Changes

- VisitClosureService owns the shared, facility/patient/visit-scoped released/unreviewed result query, excluding reception-direct orders. The facility review setting remains required. Pending unreleased clinician work still blocks closure as AwaitingResults.
- When closure selects required doctor review, it creates or reuses an active OPD queue and restores current department/status. Existing payment, admission and laboratory priorities remain ahead of review; procedure and pharmacy follow review.
- Review destinations prefer the completed OPD parent's department, with a configured active OPD fallback. A new laboratory FollowUp requires a completed OPD parent and inherits visit, patient and facility identity. Stale nonclinical departments cannot become the review destination.
- Consultation mount evaluates pending review after permission/facility checks. Workflow validation becomes HTTP 409 with an explanatory message. Route-model binding still handles missing visits as 404; authorization remains 403.
- Completion marks only released/unreviewed results belonging to that follow-up's parent and the same visit/patient/facility, then evaluates closure once. Release/verification timestamps and result values are untouched.
- The standalone clinician result-review action preserves the first review timestamp and actor on retries.
- OPD permissions apply to OPD FollowUp encounters through the existing facility policy. Queue entry also checks opd.consult in the service.

## Transactions and limits

Release, opening, closure and completion serialize on the visit row. Release and clinician result review acquire the visit lock before the result lock. Existing WorkflowService queue creation reuses an active destination queue under the same visit lock. Encounter opening returns the existing encounter for its clinician; a competing clinician gets a workflow conflict. Completed encounter retries return without repeating result review or queue completion.

The SQLite regression suite verifies sequential retries and state transitions. It does not demonstrate simultaneous row-lock behavior on a production database. No schema constraint or migration is added.

## Safe recovery

1. Deploy the application changes using the normal release process. No database migration or repair command is required.
2. Sign in as an authorized OPD clinician in the affected facility.
3. Open the existing visit's consultation URL, for example `/opd/consultations/148`. The number is a Visit ID.
4. For the supplied state, normal mount/encounter creation restores an OPD queue and opens a FollowUp linked to the completed OPD parent. It does not reopen or rewrite that parent.
5. Review the released results and complete the follow-up normally. Pending Pharmacy work is retained and becomes current when it is the next blocker. The visit closes only when remaining blockers are resolved.
6. If a configuration/parent conflict is reported, resolve the clinical/configuration issue through normal authorized application processes; do not relabel historical encounters or edit database rows to bypass it.

No production rows were edited while developing this change. No financial, stock, prescription, laboratory-value or completed clinical/queue history is deleted or rewritten by the repair. Normal review metadata and newly completed review queues/encounters are recorded through the application workflow.

## Final verification

All results below are from completed executions using the repository's SQLite in-memory PHPUnit configuration. The broad run loaded the earlier regression test fixtures; their three snapshot failures were corrected and superseded by the final focused run. Two existing clinical tests were updated to assert the required 409 workflow response (also checking no encounter was created) and to use an authorized second clinician when testing duplicate prevention. No production implementation changed during final verification.

| Execution | Tests | Assertions | Result |
| --- | ---: | ---: | --- |
| ClinicalEncounters, Laboratory, Pharmacy and Workflow directories | 198 | 1,434 | 189 passed, 7 failures, 2 errors before focused corrections |
| Final laboratory doctor-review, route and OPD-only policy regressions | 5 | 101 | Passed |
| Final clinical conflict, competing clinician, facility, permission and historical-access regressions | 5 | 27 | Passed |
| DentalPrescriptionWorkflowTest | 7 | 61 | Passed |
| Four unrelated failures with original workflow classes loaded from temporary files | 4 | 3 | Same 2 failures and 2 errors reproduced |

Exact commands:

```sh
php vendor/bin/phpunit tests/Feature/ClinicalEncounters tests/Feature/Laboratory tests/Feature/Pharmacy tests/Feature/Workflow
php vendor/bin/phpunit tests/Feature/Laboratory/Step7LaboratoryManagementTest.php --filter 'required_laboratory_review|opd_route_distinguishes|opd_only_permissions'
php vendor/bin/phpunit tests/Feature/ClinicalEncounters/Step6ClinicalWorkflowTest.php --filter 'visit_still_in_billing|two_clinicians_cannot|cross_facility_user_receives_403|missing_opd_consult_permission|completed_consultation_refreshes'
php vendor/bin/phpunit tests/Feature/Dental/DentalPrescriptionWorkflowTest.php
php vendor/bin/phpunit --bootstrap /tmp/dispensary-review-baseline/bootstrap.php tests/Feature/ClinicalEncounters/Step6ClinicalWorkflowTest.php --filter 'legacy_missing_quantity_is_visible|adding_unpaid_lab_order_keeps|completion_card_uses_next|completed_downstream_orders_do_not'
```

The broad run includes partial medication payment/dispensing, Triage Summary, diagnosis/procedure correction, direct laboratory and VisitClosure coverage. Counts above overlap and must not be summed as unique tests. The full broad run was not repeated after focused test corrections.

The four pre-existing failures, reproduced with the original workflow classes and the rest of the working tree unchanged, are:

- `test_legacy_missing_quantity_is_visible_and_livewire_edit_repairs_same_item_without_billing_duplication`: expected `Quantity: Missing` text is absent.
- `test_adding_unpaid_lab_order_keeps_encounter_open_while_visit_awaits_payment`: expects AwaitingPharmacy but receives AwaitingPayment.
- `test_completion_card_uses_next_destinations_and_user_friendly_order_statuses`: prescription quantity validation error.
- `test_completed_downstream_orders_do_not_create_new_queues`: prescription dose/quantity validation errors.

The original classes were read with `git show` into `/tmp` and loaded by a temporary PHPUnit bootstrap. No workspace reset, revert, stash, checkout or replacement was used.

Static verification:

- `vendor/bin/pint --test`: fails on 767 files with existing repository formatting issues. No repository-wide formatting was applied.
- Pint `--test` against the nine workflow-related PHP files listed below: passed.
- `php artisan view:cache`: passed.
- `php -l` on all 19 changed PHP files, including the changed Blade file: passed.
- `git diff --check`: passed.
- No migration, Visit-148-specific branch, manual SQL or direct production-state mutation was introduced.
- The ten unrelated file diffs are byte-for-byte identical to the snapshot taken at the start of final verification. Prior unrelated edits in the clinical test file remain preserved.

Files changed for this workflow task:

1. `app/Services/VisitClosureService.php`
2. `app/Services/ClinicalEncounterService.php`
3. `app/Services/LaboratoryResultReleaseService.php`
4. `app/Livewire/Opd/Consultation.php`
5. `app/Livewire/Opd/Queue.php`
6. `app/Livewire/Clinical/LaboratoryResults.php`
7. `app/Policies/ClinicalEncounterPolicy.php`
8. `tests/Feature/Laboratory/Step7LaboratoryManagementTest.php`
9. `tests/Feature/ClinicalEncounters/Step6ClinicalWorkflowTest.php` (only workflow-specific test adjustments; existing unrelated test edits preserved)
10. `docs/laboratory-doctor-review-workflow.md`

Deployment cache handling: after deploying through the normal release process, rebuild configuration and compiled views using `php artisan config:cache` and `php artisan view:cache`, and restart long-running application workers if used. No migration or data-repair command is required. Then follow the recovery steps above for `/opd/consultations/148`.
