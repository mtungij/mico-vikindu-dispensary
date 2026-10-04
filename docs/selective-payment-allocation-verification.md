# Selective payment allocation — verification report

## Outcome and deployment

The cashier can select invoice items and assign exact amounts. Confirmed allocations are persisted in the existing `payment_allocations` table. Laboratories and procedures use linked-charge eligibility; pharmacy retains its existing proportional quantity clearance and FEFO dispensing.

**No migration was required. Existing allocation persistence was sufficient. There is no new `php artisan migrate --force` requirement for this change.** No production deployment or production-data correction was performed.

## Root cause established from this checkout

1. The Receive Payment form accepted only an invoice-level amount, method, and reference. Staff could not communicate their choice of tests or medicines.
2. `PaymentConfirmationService::allocateToItems()` selected active items automatically, prioritizing `PrescriptionItem` references and then ascending IDs. The first medicine lines could receive money regardless of patient choice.
3. Laboratory sample/result authorization relied on `LaboratoryOrder.payment_status`, not each test's `invoice_item_id`. The release service updated every nonterminal item in a released order. The queue and collection picker also used order-level eligibility; a user with `laboratory.override-payment` could process unpaid tests.

Accuracy limitation: this checkout's ordinary cash-payment release path required full invoice payment; it did **not** explicitly release all tests on `payment_status = partial`. The source establishes missing selection and unsafe order-level/override authorization. Without the production records, deployed revision, and actor permissions, the precise production branch used by staff cannot be proven. The regression now enforces their reported requirement regardless of that branch.

## Architecture and behavior

- `InvoiceItem.patient_amount` is patient responsibility; `insurance_amount` and `payer_amount` remain separate. `paid_amount` remains derived from confirmed, unreversed item allocations by the existing `InvoiceStatusService`.
- `PaymentAllocation` already supports payment, invoice, invoice item, facility, amount, actor, timestamps, and reversal information. Explicit allocation uses these existing fields; confirmed payments record `metadata.allocation_mode = explicit`. Existing automatic callers retain their prior allocation method when they do not supply an explicit allocation payload.
- The cashier modal groups lines by item type and shows patient total, previously paid, outstanding amount, select checkbox, and editable Pay Now. Cleared/inactive lines cannot be selected. Selected total, amount received, and remaining invoice balance are visible. Reopening shows PAID/CLEARED, PARTIALLY PAID, and UNPAID. Explicit mode requires exact amount equality; it does not spread leftovers or accept over-tender as an allocation.
- Each active lab item clears only when its own active charge has `paid_amount >= patient_amount` and any required preauthorization is present. An order may contain both ready and awaiting-payment items. Collection, sample acceptance, result entry, verification, and release check item eligibility; the queue and picker hide unpaid actions. Unselected tests remain awaiting payment, separate from Cancel Test and Not Performed.
- Cashier explicit selection cannot be bypassed by the legacy lab payment-override permission. The old separately audited service override remains available outside explicit-selection invoices; it does not cause the normal unpaid UI to expose actions.
- Zero patient responsibility clears legitimate free/covered tests without a fake zero payment. Coverage approval remains in the existing order/coverage workflow. Existing insurance/corporate/waiver and medicine copay/preauthorization regressions were exercised.
- Medicine clearance is unchanged: eligible paid quantity is the prescribed quantity multiplied by the paid fraction of patient liability, floored to the dispensing unit's allowed precision, less quantity already dispensed. Only explicitly selected lines receive additional paid amounts. The quantity shortcut converts an additional quantity into a monetary allocation, rounded upward to cents, without changing invoice quantity, total, or prescription quantity. Ten of thirty tablets at TSh 100 each allocates TSh 1,000 and clears ten tablets.
- Procedures awaiting payment now release only if their own linked active charge is cleared; unrelated outstanding charges do not release or block that selected procedure.
- Invoice conventions remain unchanged: `status = partially_paid`, `payment_status = partial` during partial payment; both become paid at completion.

## Exact production scenarios

**Lab:** One order contains Malaria 5,000, Hemoglobin 4,000, Urinalysis 3,000, RPR 4,000. The cashier UI selects the first two and confirms 9,000. Invoice paid/balance is 9,000/7,000; only those two tests clear. Urinalysis/RPR remain awaiting payment and sample/result service calls reject them, including an attempted override of explicit selection. A second 7,000 allocation selects the remaining two. Paid/balance becomes 16,000/0; remaining tests clear. First sample state and first payment/allocation records are preserved. There is one invoice, one order, four invoice items, two payments, four allocations, and one lab queue.

**Medicines:** Four lines total 10,000. Selection pays Diclofenac 4,000 and Paracetamol 1,000, which are the last two lines. The first two remain uncleared; dispensing an unselected line is rejected. Stock stays at 40 units after payment, falls to 38 only after dispensing those two units, and falls to 37 only after a later payment and dispensing of one additional line. Previously dispensed quantity is not available again. The separate 10-of-30 UI test verifies the unchanged 30-unit/3,000 charge and 1,000 paid amount.

**Mixed invoice:** Registration, consultation, two configured lab tests, medicine, and procedure share one invoice. First selective payment covers registration, one lab, and one medicine unit. Unselected lab, consultation, and procedure remain unpaid. A later procedure-only payment releases that procedure while other balances remain. Retry creates no duplicate payment, allocation, or queue; attempting to pay the already-cleared registration from stale state fails. The same Partial Consultation remains `in_progress`, has no `completed_at`, is not read-only, and accepts a resumed doctor's draft.

## Transactions, retry, and historical safety

Payment confirmation locks the invoice and its items, recalculates under the transaction, and locks selected rows again before persisting. It rejects missing/foreign-invoice/foreign-facility items, inactive or fully-paid lines, over-allocation, nonpositive amounts, extra decimal places, empty selections, and selected-total mismatch. Same-key retries return the existing payment; changing method, amount, invoice, or selected allocations is rejected. The existing facility/idempotency unique constraint remains in use.

Stale-model and repeat-payment tests verify revalidation and rollback. Tests use isolated SQLite databases; they do not simulate simultaneous MySQL sessions. MySQL row-lock behavior is enforced by the transaction/`lockForUpdate` implementation, not established by a live concurrency load test.

No migration, backfill, redistribution, or production data-rewrite job was added or run. Earlier payment/allocation snapshots remain byte-for-byte unchanged in the two-payment and mixed-invoice regressions. The existing allocation recalculation algorithm was not rewritten.

Historical compatibility is read-only: an already-paid legacy lab order backed by a confirmed pre-existing payment with no item allocations and no new allocation-mode marker retains its previous clearance. This does not fabricate item `paid_amount` or allocations and does not clear a newly pending order. Legacy orders without linked charges retain the previous allowed order-level states. New explicit payments remain governed by linked items.

## Tests and results

Focused new regressions initially passed **5 tests / 89 assertions**:

- `test_production_partial_cash_selection_does_not_open_unselected_lab_tests_until_second_payment`
- `test_selective_allocations_reject_invalid_items_amounts_and_changed_retry_without_writes`
- `test_free_lab_item_is_ready_without_payment_on_a_mixed_unpaid_invoice`
- `test_production_selective_medicine_payment_clears_only_chosen_lines_and_stock_changes_only_on_dispense`
- `test_cashier_can_pay_for_ten_of_thirty_tablets_without_rewriting_billed_quantity`

Subsequent strengthened tests add unchanged-history assertions, explicit-selection override rejection, and:

- `test_mixed_invoice_selective_payment_keeps_partial_consultation_open_and_releases_only_selected_items` — **1 passed / 30 assertions**.
- `test_legacy_paid_lab_without_item_allocations_keeps_clearance_without_backfill_or_clearing_new_tests` — final laboratory run recorded below.

Broader executed runs:

| Selection | Result |
| --- | --- |
| Billing cashier + cashier session + laboratory payment + dental prescription files | Initial 47/48 passed, 323 assertions; only failure was the intentionally replaced unpaid-lab UI expectation. All 27 Billing/session and 9 Dental cases passed. |
| Laboratory payment file after expectation correction and stronger history checks | 12/12 passed, 131 assertions; superseded by final historical-safety run below. |
| Pharmacy inventory: receiving, FEFO, dispensing, partial dispensing, partial payment/later payment, reversal | 6/6 passed, 56 assertions. |
| Selected laboratory collection, doctor review, terminal decision, Partial Consultation | Initial 6/7 passed, 127 assertions; the remaining fixture marked an order paid without allocations. Corrected to create a real allocated payment; rerun passed 1/1, 10 assertions. |
| Selected OPD cash partial payment, insurance/copay, Partial Consultation, mixed invoice | Initial 3/4 passed, 36 assertions; mixed fixture lacked a configured procedure destination. Corrected mixed test passed 1/1, 30 assertions. |

The entire slow Clinical workflow file was not run. The two corrected fixtures now model legitimate payment and configured department routing; production behavior was not weakened to satisfy them.

**Final historical-safety result:** the laboratory suite passed the other 12 cases again; the new historical test initially compared an unrefreshed model with a database snapshot. After normalizing both snapshots, its targeted rerun passed **1 test / 13 assertions**. All **13 laboratory payment cases** have passing results; no failures remain unresolved. Across the selected Billing/session, Dental, laboratory payment, Pharmacy, laboratory clinical, and OPD runs, **66 distinct test cases passed**, using targeted reruns for corrected fixtures. This is not a claim that a single 66-test invocation was run.

## Static verification

Pint formatting and `pint --test` passed for all 18 touched non-Blade PHP files, including files already committed in the resumed baseline. PHP syntax checks passed for all 18. `php artisan view:cache` and `git diff --check` passed. Final checks include the historical-compatibility addition.

## Files changed across this task

- `app/Livewire/Billing/Invoices/Show.php`
- `app/Livewire/Laboratory/Queue.php`
- `app/Livewire/Laboratory/ResultEntry.php`
- `app/Livewire/Laboratory/VerifyResult.php`
- `app/Models/LaboratoryOrderItem.php`
- `app/Services/LaboratoryOrderService.php`
- `app/Services/LaboratoryPaymentGuard.php`
- `app/Services/LaboratoryPaymentReleaseService.php`
- `app/Services/LaboratoryResultReleaseService.php`
- `app/Services/LaboratoryResultService.php`
- `app/Services/LaboratoryResultVerificationService.php`
- `app/Services/LaboratorySampleService.php`
- `app/Services/PaymentConfirmationService.php`
- `app/Services/ProcedureOrderService.php`
- `resources/views/livewire/billing/invoices/show.blade.php`
- `resources/views/livewire/laboratory/order-show.blade.php`
- `resources/views/livewire/laboratory/queue.blade.php`
- `tests/Feature/ClinicalEncounters/Step6ClinicalWorkflowTest.php`
- `tests/Feature/Dental/DentalPrescriptionWorkflowTest.php`
- `tests/Feature/Laboratory/LaboratoryPaymentWorkflowTest.php`
- `tests/Feature/Laboratory/Step7LaboratoryManagementTest.php`

- `docs/selective-payment-allocation-verification.md`

This resumed completion preserved all prior edits. It additionally corrected the two regression fixtures and added the legacy-history compatibility check/test and this report. No historical payments, invoices, prescriptions, lab results, or stock movements were destructively rewritten. Stock mutations in acceptance tests occur only through the existing actual-dispensing workflow in isolated test databases.
