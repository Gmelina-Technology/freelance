# Spec: batch-sales-conversion (build 2026-10-11-batch-sales-conversion)

**Version:** v1 (first assessment, 2026-10-11)
**Evaluated:** `design.md` against `plan.md` (Tier: Standard), `intake.md`, `intake-sources/user-clarifications.md` (UC), `.ai/rules/services.md`, and the shipped single-sale conversion code (commit 4ede53c): `SaleConversionService`, `SaleConversionValidator`, `SaleConversionCalculator`, `SaveSaleConversionAction`, `ConvertSaleAction`, `SaleConversionArchTest`, `sale_conversions` and `sales` migrations.

---

## Verdict

**Verdict: PASS**

**Risk tier: MEDIUM**

Root cause category: n/a (passing).

The pass is conditional on the acceptance criteria below. **AC-S3 (overflow guard) and AC-M4 (proven mid-loop rollback) close two gaps in `design.md`.** QA must treat them as blocking, the same as any other criterion. Both are small fixes the Developer can make inside the design's own seams (`inputAtRate()` and the `handleMany()` tests). They don't change the architecture, so I didn't bounce the design to Architect for them.

Risk-tier rationale: the build doesn't touch auth (it reuses the existing `isMember` gate unchanged), payments processing, schema or migrations (D9 confirmed: no columns added), or any public API. It does write financial records for up to 100 rows in one operation, so it's above Low. The human gate already happens for OQ1–OQ4.

---

## Five-dimension verdict

### 1. Feasibility: PASS
- Additive only: 2 new classes (a Filament bulk action and a form schema), plus new methods on 4 existing classes, plus tests. No new dependency, model, migration or route. This fits the plan's ceiling of about 3 engineer-days (P§5). The build sequence maps cleanly onto M1–M4.
- Fits the stack and rules:
  - `Account` is the first explicit argument to `convertBatch()` (services.md).
  - No `DB` or `Validator` facade in `SaleConversionService` (arch test line 14–16).
  - No new Action class. `handleMany()` goes on the existing `SaveSaleConversionAction`.
  - `ConvertSalesBulkAction` is a Filament UI action builder that follows the `ConvertSaleAction` precedent. It isn't a business-logic Action class in `app/Services`, so services.md doesn't forbid it.
- Bulk-action test API is version-specific. The design already says to confirm it via `search-docs` before step 7. Accepted.

### 2. Scalability: PASS
- The hard cap of 100 (`MAX_BATCH_SIZE`) bounds the work per request regardless of the size of the table or tenant. At 10x users or 10x sales, each request still does at most about 400 short statements. Row locks cover only the selected `sales` rows, taken in id order, so concurrent batches serialize without deadlock and don't block unrelated rows.
- Synchronous, in-request execution is right at this cap. A queue would add failure modes (partial visibility, async notification) that conflict with all-or-nothing feedback (N5).

### 3. Maintainability: PASS
- Reuses `validate()`, `fromInput()` and `handle()` unchanged. A single seam (`inputAtRate()`) turns a shared rate into per-sale input, so the OQ1 options (a/b/c) only change that method and the form labels.
- The validator stays the source of truth, and the form mirrors it (the existing convention).
- The new arch rule stops the bulk action from bypassing the service.
- The stored rate is recomputed as gross / amount. That keeps one meaning for `exchange_rate` across the table. This is the right call.
- Minor, non-blocking: `handle()` re-locks each sale that `handleMany()` already locked. That's redundant but harmless, and it's the price of keeping `handle()` unchanged (C5).

### 4. Security: PASS (with AC-S3 binding)
- **Tenant isolation:**
  - Selected keys are resolved through Filament's tenant-scoped table query.
  - The service re-checks `account_id` (step 3, defence in depth).
  - `handleMany()` re-reads only ids that already passed that check.
- **AuthZ:** the bulk action uses the same gate as single conversion: `! Auth::user()->isMember(tenant)`. Members are already forbidden from the list page.
- **Mass assignment:**
  - `validateBatch()` and `validate()` both allow-list keys.
  - `handle()` maps every column explicitly.
  - `converted_by` comes from `Auth::user()` only.
  - Design step 5 case 11 tests the allow-list.
- **Output:** notification bodies carry exception messages that interpolate `reference_key` and currency codes. Filament notifications sanitize string bodies. This is the same pattern as the existing `ConvertSaleAction` (line 41).
- **Gap closed by AC-S3:** integer overflow in the rate math. `validateBatch()` allows `exchange_rate` up to `MAX_AMOUNT` (about 1e13), and `sales.amount` is `decimal(12,2)` (up to about 1e12 cents). `inputAtRate()` computes `(int) round(toCents($sale->amount) * $rate)`. Once the product passes `PHP_INT_MAX` (about 9.2e18 cents), the `(int)` cast wraps or returns undefined values. That happens with, for example, a sale of 10,000,000.00 and a rate of 1e7, which a mistyped rate could produce. The wrapped value can be a positive, in-range number. Then the existing `validate()` checks (`max:MAX_AMOUNT`, combined gross) run on that already-corrupted string and accept it, and a wrong financial figure is persisted. That violates G3 ("never … wrongly converted records"). The design's section 2.3 "known limitation" note only considers half-cent rounding and misses this. The single-sale path isn't affected because there the user enters `converted_amount` directly and it's bounded before any cast.

### 5. Performance: PASS
- Phase 1:
  - One eager load: `loadMissing('saleConversion')`.
  - In-memory checks.
  - Per-sale `Validator::make` with no DB rules, because `currency_code` is excluded when the sale's currency is known, and unknown-currency sales are blocked earlier.
- Phase 2: one transaction, about 4 statements per sale. At the 100 cap that's sub-second on MySQL.
- No N+1, and no lazy loading on the happy path.
- Rejected selections fail before any write and before any lock.

---

## Acceptance criteria (QA checks the implementation against these)

### Feasibility / scope
- **AC-F1.** The only production files touched are the 8 listed in design §1.2. No migration, model, factory, enum, route, config, `composer.json` or `package.json` change.
- **AC-F2.** `ConvertSaleAction`, `EditSaleConversionAction`, `SaleConversionService::convert()`, `guardAccountCurrency()`, `SaleConversionValidator::validate()`, `SaleConversionCalculator::fromInput()`/`grossCents()` and `SaveSaleConversionAction::handle()` are byte-for-byte unchanged in `git diff main` (C5).
- **AC-F3.** All pre-existing tests in `tests/Feature/Sales/` pass without modification. Only additions are allowed in the existing test files.

### Scalability
- **AC-SC1.** `SaleConversionService::MAX_BATCH_SIZE === 100`. A selection of 101 throws `batchTooLarge`, makes 0 writes and takes 0 row locks (the check runs before `handleMany()`).
- **AC-SC2.** `handleMany()` locks rows with `whereKey($ids)->orderBy('id')->lockForUpdate()` inside a single `DB::transaction`.

### Maintainability
- **AC-M1.** `SaleConversionService` doesn't use `Illuminate\Support\Facades\DB` or `Validator` (the existing arch rule still passes).
- **AC-M2.** A new arch rule asserts that `ConvertSalesBulkAction` doesn't use `SaveSaleConversionAction` or the `DB` facade.
- **AC-M3.** Rate-to-input logic (OQ1 semantics) exists only in `SaleConversionCalculator::inputAtRate()`. No fee/tax arithmetic appears in the service, the bulk action or the form.
- **AC-M4.** A test proves **mid-loop** rollback in `handleMany()`:
  - At least one item's `handle()` has already written a row.
  - A later item then throws.
  - The test asserts `SaleConversion::count()` is unchanged and the earlier sale has no conversion.

  The `batchSelectionChanged` case in design §6 step 4 doesn't satisfy this, because it throws before any `handle()` call. Trigger the failure some other way, for example a `SaleConversion::creating` listener registered in the test that throws on the Nth insert.

### Security
- **AC-S1.** The bulk action's `authorize` is `! Auth::user()->isMember(Filament::getTenant())`. A test asserts it's unauthorized for a member and authorized for owner and manager.
- **AC-S2.** `convertBatch()` throws `batchHasForeignSales` and makes 0 writes when any sale's `account_id` differs from the `Account` passed in, even when the caller bypasses Filament (service-level test).
- **AC-S3 (binding, closes the overflow gap).**
  - Before any `(int)` cast, a sale whose `amount × exchange_rate` would be above `SaleConversion::MAX_AMOUNT` is rejected. It surfaces as `invalidSaleInBatch` naming that sale, and the batch makes 0 writes.
  - The guard must compare in float or string space, for example `(float) $sale->amount * (float) $rate > (float) SaleConversion::MAX_AMOUNT`, never on an already-cast int.
  - Tests:
    - a sale of 10,000,000.00 with rate `9999999999999` is rejected with 0 rows written;
    - a sale whose product lands exactly at or just under `MAX_AMOUNT` (net inside limits) still converts.
- **AC-S4.** Extra keys in `$data` are ignored: `converted_amount`, `converted_by`, `account_id`, `currency_code`, `converted_currency_code`. Each stored row has `converted_by` equal to the acting user and `converted_currency_code` equal to the account currency (design step 5 case 11).
- **AC-S5.** A failure notification body is the exception message, passed as a plain string (not `HtmlString`). This matches `ConvertSaleAction`.

### Performance
- **AC-P1.**
  - `convertBatch()` eager-loads `saleConversion` once for the whole collection.
  - With `Model::preventLazyLoading()` active, as in tests, a 3-sale happy-path batch raises no lazy-loading violation.
- **AC-P2.** Every blocking selection or input check (design §3.1 steps 1–10) completes before `handleMany()` is called. A test for each blocking case asserts 0 rows written.

### Functional (from plan T1–T6, UC)
- **AC-FN1.** Happy path: N same-currency, unconverted, non-account-currency sales all get exactly one `sale_conversions` row each. In each row:
  - `gross_amount = round_half_up(amount × rate, 2)`;
  - `converted_amount = gross − fee − tax`;
  - `exchange_rate = gross / amount` (10 dp);
  - `converted_at` and `converted_by` are set.

  The user gets one success notification, "Sales converted", with body "{n} sales converted into {CODE}.", and records are deselected.
- **AC-FN2.** Each of these produces one danger notification, "Sales not converted", whose body names the reason and the fix (§4.3 messages), and 0 rows are written:
  - mixed source currencies;
  - any already-converted sale;
  - any sale already in the account currency;
  - any sale with `currency_code` null;
  - missing account currency;
  - empty selection.

  Existing conversions stay unchanged.
- **AC-FN3.** An invalid batch input (rate missing, 0, non-numeric, or more than 10 dp; fee/tax negative or more than 2 dp) shows as a form field error on that field, with 0 rows written.
- **AC-FN4.** A per-sale limit breach, where net ≤ 0 after fee/tax, raises `invalidSaleInBatch`. The message names the sale by `reference_key`, or `#id` when there's no reference key. 0 rows are written for **all** selected sales.
- **AC-FN5.** Race: if a selected sale gains a conversion between phase 1 and phase 2, `handleMany()` throws `batchHasConvertedSales`, writes nothing, and leaves the existing conversion unmodified.
- **AC-FN6.** With no account currency, the bulk action is disabled and shows the tooltip "Set your account currency in Account Profile first."

---

## Open questions for the human approval gate

These are carried from `design.md` §7 / `plan.md` §8. The design's defaults are sound, so none of them is a failure. They need a human decision before implementation starts.

- **OQ1. Fee/tax semantics.** The default is (a): the same absolute fee and tax on each sale, in the account currency. The alternatives are (b), a batch total split pro-rata (about +0.5 day, localized to `inputAtRate()` and the labels), and (c), a percentage. Please confirm (a). Tie-in with OQ5: if batches exist to reconcile against one bank deposit with one bank fee, (b) is the better fit.
- **OQ2. Unknown-currency sales.** The default is (a): they block the batch and must be converted one at a time. Please confirm.
- **OQ3. Permissions.** The default is the same as single conversion: owners and managers only, not members. I'm satisfied with this from a security standpoint. Please confirm it matches intent.
- **OQ4. Batch size cap.** The default is 100, stored as a constant. Please confirm, or name a different number. The mechanism doesn't change.
- **OQ5. Business driver.** Is the driver efficiency or deposit reconciliation? This decides OQ1.

---

## Change log
- v1 (2026-10-11): first assessment. PASS, Risk tier MEDIUM. AC-S3 and AC-M4 added as binding criteria for gaps found in `design.md` §2.3 and §6 step 4.
