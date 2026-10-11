# QA Review: batch-sales-conversion (build 2026-10-11-batch-sales-conversion)

**Pass 1:** 2026-10-11
**Reviewed:** the uncommitted working tree against HEAD `d6bdf74` (the merge-base with `main`), plus the untracked files. Judged against `spec.md` v1. `design.md` was used for reference.
**Gate decisions applied:**
- OQ1(a): the same absolute fee and tax on each sale.
- OQ2(a): unknown-currency sales block the batch.
- OQ3: same authorization as single conversion.
- OQ4: a cap of 100.

**Pass 2:** 2026-10-11 (retry review after Developer fix for AC-P2)

## Pass 2 overall verdict: PASS

All acceptance criteria in `spec.md` v1 are now met. Pass 1's only failure was AC-P2, and it is fixed. Nothing else regressed.

### Pass 2 scope and evidence
- Working tree re-checked against merge-base `d6bdf74` (HEAD). `git diff HEAD --numstat` still shows **0 deleted lines** in all 11 modified files. The untracked files are the same 3 as in pass 1: `SaleBatchConversionForm.php`, `ConvertSalesBulkAction.php`, `SaleBatchConversionServiceTest.php`.
- The production files are unchanged since pass 1. Their mtimes (latest `SaleConversionService.php`, 23:20) are earlier than the pass-1 `review.md` (23:27). The diff content matches the line-level evidence cited in pass 1. The only file changed after pass 1 is `tests/Feature/Sales/SaleBatchConversionServiceTest.php` (23:28).
- `php84 -d memory_limit=1G vendor/bin/pest tests/Feature/Sales`: **102 passed (448 assertions)**. Pass 1 had 447 assertions; the extra one is the new 0-rows assertion.
- `--filter="size cap"`: 1 passed (4 assertions).
- The orchestrator's validator reports Pint clean and the full suite passing (470).

### AC-P2: now PASS
- `SaleBatchConversionServiceTest.php:120-128` ("refuses a batch above the size cap") now:
  - builds 101 **persisted** sales with the factory: `batchSales($this->account, SaleConversionService::MAX_BATCH_SIZE + 1)`, all EUR, unconverted, amount 1000;
  - asserts `MAX_BATCH_SIZE === 100` and the `batchTooLarge` throw ("at most 100 sales");
  - asserts `SaleConversion::count()` is `0` (line 127).
- The assertion isn't trivially true. All 101 sales are eligible and valid at rate 2 (gross 2000.00). If the cap check at `SaleConversionService::convertBatch()` were removed, the batch would write 101 rows and the test would fail.
- Every other blocking case still asserts 0 rows or an unchanged count:
  - the dataset for mixed, already converted, account currency, unknown currency and empty selection (lines 59-88);
  - no account currency (101-108);
  - foreign sales (110-118);
  - invalid batch input (130-135);
  - per-sale limit breach (137-145);
  - overflow (147-153);
  - the race (`SaveSaleConversionActionTest`).
- The code half is unchanged and still holds: every blocking check in `convertBatch()` runs before the single `SaveSaleConversionAction::handleMany()` call at the end of the method.

### Regression check (all other ACs)
I re-confirmed the following on the current tree. All remain **PASS**, with the same evidence as in pass 1 below.
- **Scope:** AC-F1, F2, F3.
- **Scalability:** SC1, SC2.
- **Maintainability:** M1, M2, M3, M4.
- **Security:** S1, S2, S3, S4, S5.
- **Performance:** P1.
- **Functional:** FN1 through FN6.

Pass 1 cited line numbers inside `SaleConversionService.php`; those may be off by a few lines but don't change any finding. Notes N1-N5 below still stand as non-blocking items for human attention.

---

## Pass 1 overall verdict (superseded by pass 2): FAIL (one minor, test-only gap)

The production code meets every acceptance criterion, including both binding ones:
- **AC-S3:** the overflow guard.
- **AC-M4:** proven rollback partway through the loop.

One criterion fails as written: **AC-P2**. The test for the size cap doesn't assert that 0 rows were written. It's a one-line test fix, and no production change is needed.

### Validation evidence
- I re-ran `php -d memory_limit=1G vendor/bin/pest tests/Feature/Sales`. Result: **102 passed (447 assertions)**.
- The orchestrator's validator had already reported Pint clean and the full suite passing (470).
- `git diff HEAD --numstat` shows **0 deleted lines** in every changed file. Every change is an addition.
- Note on the diff baseline: `main` has moved ahead by one commit (`bd1eed0 update theme`). Because of that, `git diff main` also lists `public/build/*` asset changes. Those changes come from `main`, not from this branch. Against the merge-base, the branch touches only the files listed below.

---

## Per-criterion findings

### Feasibility / scope
| AC | Result | Evidence |
|----|--------|----------|
| AC-F1 | PASS | Exactly 8 production files changed. 6 were modified: `SaleConversionException`, `SaveSaleConversionAction`, `SaleResource`, `SaleConversionService`, `SaleConversionCalculator`, `SaleConversionValidator`. 2 are new: `Common/Schemas/SaleBatchConversionForm.php`, `Resources/Sales/Actions/ConvertSalesBulkAction.php`. There are no migration, model, factory, enum, route, config, composer or package changes. |
| AC-F2 | PASS | `ConvertSaleAction.php` and `EditSaleConversionAction.php` aren't in the diff. `convert()`, `guardAccountCurrency()`, `validate()`, `fromInput()`, `grossCents()` and `handle()` sit in hunks that are purely additive (0 deleted lines). |
| AC-F3 | PASS | The 5 edited test files only gained lines (0 deletions), and every pre-existing Sales test passes. |

### Scalability
| AC | Result | Evidence |
|----|--------|----------|
| AC-SC1 | PASS | `SaleConversionService::MAX_BATCH_SIZE = 100` (SaleConversionService.php:19). The cap check (lines 71-73) runs before `handleMany()` (line 113), so the 101st sale causes no write and takes no lock. Test: `SaleBatchConversionServiceTest.php:121-127`. See AC-P2 for the missing 0-rows assertion. |
| AC-SC2 | PASS | `SaveSaleConversionAction.php:78-79`: `DB::transaction(... Sale::query()->whereKey($ids)->orderBy('id')->lockForUpdate()->get())`. |

### Maintainability
| AC | Result | Evidence |
|----|--------|----------|
| AC-M1 | PASS | The service imports neither `DB` nor `Validator`. The existing arch rule passes. |
| AC-M2 | PASS | `SaleConversionArchTest.php:19-21` asserts that `ConvertSalesBulkAction` uses neither `SaveSaleConversionAction` nor the `DB` facade. The rule passes. |
| AC-M3 | PASS | The rate, fee and tax arithmetic lives only in `SaleConversionCalculator::inputAtRate()` (lines 57-79). The service, the bulk action and the form contain no fee or tax math. |
| AC-M4 | PASS | `SaveSaleConversionActionTest.php` covers it in "rolls back earlier rows when a later save fails partway through the batch":<br>- A `SaleConversion::creating` listener throws on the 3rd insert.<br>- The test asserts `$inserted === 3`, which proves 2 rows were written through `handle()` first.<br>- It then asserts `SaleConversion::count() === 0` and that the first sale is not converted. |

### Security
| AC | Result | Evidence |
|----|--------|----------|
| AC-S1 | PASS | `ConvertSalesBulkAction.php:24` has the gate `! Auth::user()->isMember(Filament::getTenant())`. "keeps members from the bulk action" asserts the member is unauthorized and the owner is authorized. The manager's authorization is shown by the owner/manager dataset of the happy-path bulk test, which succeeds. |
| AC-S2 | PASS | The check is at `SaleConversionService.php:75-77`. "refuses sales that belong to another account" is a service-level test with no Filament, and it asserts 0 rows. |
| AC-S3 | PASS | `SaleConversionCalculator.php:62` makes a float comparison `(float) $sale->amount * $rate > (float) MAX_AMOUNT`. It runs before the `(int)` cast at line 71 and throws `invalidSaleInBatch($sale, ...)`. Tests:<br>- `SaleBatchConversionServiceTest.php:146-159`: 10,000,000.00 × `9999999999999` is rejected with "Sale #id", and 0 rows are written.<br>- The same test converts 1000 × `9999999999.99` (gross `9999999999990.00`, just under MAX).<br>- The calculator unit test covers the same overflow. |
| AC-S4 | PASS | "ignores keys outside the batch input" sends `converted_amount`, `converted_by`, `converted_currency_code`, `currency_code` and `account_id`. The stored row has the computed net, `converted_by` set to the acting user, `converted_currency_code = PHP`, and the sale's currency unchanged. The validator also has its own allow-list test. |
| AC-S5 | PASS | `ConvertSalesBulkAction.php:39` uses `->body($exception->getMessage())`, a plain string. This matches `ConvertSaleAction`. |

### Performance
| AC | Result | Evidence |
|----|--------|----------|
| AC-P1 | PASS | `SaleConversionService.php:79` calls `$sales->loadMissing('saleConversion')` once. Nothing in phase 1 or phase 2 lazy-loads: `handle()` uses explicit queries and `setRelation`. The happy-path Livewire test runs with `preventLazyLoading` on, through `AppServiceProvider:44`, on records hydrated by a query, and it passes. See note N2 about the service-level test. |
| AC-P2 | **FAIL** (test gap only) | The code meets the first half: steps 1-10 all run before `handleMany()` (`SaleConversionService.php:61-111`). Every blocking case has a test that asserts `SaleConversion::count()` is unchanged, **except the size cap**. `SaleBatchConversionServiceTest.php:121-127` ("refuses a batch above the size cap") asserts only the exception and the constant, never 0 rows written. The scenario: a 101-sale selection → `batchTooLarge`, with no row-count assertion. The criterion says "A test for each blocking case asserts 0 rows written", so this fails as written. **Fix:** add `expect(SaleConversion::count())->toBe(0)` to that test. Ideally, build the 101 sales with the factory (`batchSales($this->account, 101)`) instead of unsaved `new Sale(...)` instances. Otherwise the assertion is trivially true, because unsaved models can't be converted anyway. |

### Functional
| AC | Result | Evidence |
|----|--------|----------|
| AC-FN1 | PASS | Service happy path with 3 sales checks gross, net, fee, tax, rate (`56.5000000000` = gross/amount), note, currency, `converted_by` and `converted_at`. The calculator tests cover half-up rounding (33.33 × 0.9 → 30.00). On the UI side: the bulk test with 2 sales asserts "Sales converted". The body `"{n} sales converted into {CODE}."` is at `ConvertSalesBulkAction.php:46`, and `deselectRecordsAfterCompletion()` is at line 29. The body text and deselection are checked by reading the code; no test asserts them. |
| AC-FN2 | PASS | Each case throws its §4.3 factory message, and the dataset "blocks the whole batch for every ineligible selection" asserts 0 rows for: mixed, already converted, account currency, unknown currency, and empty. "refuses when the account has no currency" asserts 0 rows. Every `SaleConversionException` goes through the single "Sales not converted" danger notification (line 35-42). The UI tests cover mixed and converted, and the "existing conversion unchanged" check is at `SaleBatchConversionServiceTest.php:91-100`. |
| AC-FN3 | PASS | The form mirrors `validateBatch()` (required, numeric, `gt:0`, `decimal:0,10`, max; fee and tax have `minValue(0)` and `decimal:0,2`). The UI test "shows a field error for an invalid batch rate" covers `abc`, `0` and 11 dp, with `assertHasFormErrors(['exchange_rate'])` and no new rows. The validator tests cover missing, negative and too-large rates, a negative fee, and tax with 3 dp. |
| AC-FN4 | PASS | A `ValidationException` from `validate()` is rethrown as `invalidSaleInBatch` (`SaleConversionService.php:101-105`). Tests:<br>- The `reference_key` form ("Sale INV-SMALL ...") is covered with 0 rows for all 3 sales.<br>- The `#id` form is covered in the AC-S3 test. |
| AC-FN5 | PASS | `SaveSaleConversionAction.php:85-89` re-checks `has('saleConversion')` while holding the lock. "writes nothing when a selected sale was converted in the meantime" asserts the throw, a count of 1, and that the existing row is still `77.00`. |
| AC-FN6 | PASS | The disabled state and the exact tooltip text are at `ConvertSalesBulkAction.php:25-28`. "disables the bulk action when the account has no currency" asserts the disabled state. The tooltip text is checked by reading the code. |

### Risk-tier safeguards (MEDIUM)
The spec flagged AC-S3 and AC-M4 as binding. Both are implemented and proven by tests, not just mentioned. The build doesn't touch auth: it reuses the existing gate unchanged, and there are no schema changes.

---

## Requested judgement: `convertBatch()` takes an Eloquent Collection, not a Support Collection
**This doesn't matter. The deviation is an improvement.**
- `design.md` §3.1 step 4 calls `$sales->loadMissing('saleConversion')`. That method exists only on `Illuminate\Database\Eloquent\Collection`, so the Support-Collection signature in the design contradicted its own logic.
- Filament supplies an Eloquent Collection here. `fetchSelectedRecords` defaults to true and there's no chunking, so `getSelectedTableRecords()` returns `->get()` (`vendor/filament/actions/src/Concerns/InteractsWithSelectedRecords.php:52-66`). The UI tests exercise that path.
- The only risk: if someone later adds `fetchSelectedRecords(false)` or `chunkSelectedRecords()` to this action, the call fails loudly with a `TypeError` before any write, never silently.
- One small inconsistency: the action closure type-hints `Illuminate\Support\Collection $records` (`ConvertSalesBulkAction.php:30`) but passes it to the narrower parameter. That's fine at runtime, but static analysis may flag it. It's not an AC issue.

---

## Flagged for human attention (outside strict pass/fail; these are judgement calls, not criterion failures)
- **N1. "Select all" across pages.** If a user selects every matching record (for example thousands), Filament hydrates all of them before `convertBatch()` rejects anything over 100. Nothing is written and nothing is locked, so AC-SC1 holds. The cost is the memory and query to load the selection. Consider a selection limit in the UI if the installed Filament supports one.
- **N2. A weak lazy-loading check in the service test.** `SaleBatchConversionServiceTest.php:34` turns on `Model::preventLazyLoading()`, but the sales are factory-created. Laravel only flags models hydrated from a query with more than 1 row, so this test can't catch a lazy load. AC-P1 is still met through the Livewire happy-path test. Also, the global `preventLazyLoading()` call is redundant with `AppServiceProvider`.
- **N3. Empty `reference_key`.** `invalidSaleInBatch` uses `reference_key ?? '#'.id`. A sale with an empty-string `reference_key` would show as "Sale  can't be converted…". This only matters if empty strings can be stored.
- **N4. Wording.** The body reads "1 sales converted into PHP." when one sale is selected. That's the spec's literal text, so it's not a finding.
- **N5. Strict comparison in the account check.** `$sale->account_id !== $account->getKey()` at `SaleConversionService.php:75` depends on both values being ints. That holds with PDO on PHP 8.1+ for SQLite and MySQL. If the type changes, every batch would wrongly fail as "foreign", but nothing would be written wrongly.
