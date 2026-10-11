# Plan: batch-sales-conversion (build 2026-10-11-batch-sales-conversion)

Inputs: `intake.md` (node IDs cited throughout), `intake-sources/requirement.md` (S1), `intake-sources/user-clarifications.md` (cited as **UC**; UC overrides the intake's open questions where it answers them).

**Tier: Standard**

Note: the `prd-writing` skill could not be found at `.claude/skills/prd-writing/SKILL.md` (in the worktree or at `D:\dara\.claude\skills\`), so this plan follows the PM agent brief directly.

---

## 1. Goals (the why)

- **G1. Let a user convert many sales at once.** Today a user converts one sale at a time. They should be able to convert several sales in one operation. (S1 "support batch conversion of sales"; A; existing single action A.1 / `ConvertSaleAction`.)
- **G2. Keep batch conversions consistent.** A batch only makes sense when one exchange rate fits every sale in it. The batch must therefore contain sales that share a source currency. Mixing currencies must be impossible, not merely discouraged. (S1 "but only with those same currencies"; B, B.1; UC on B/B.4.)
- **G3. Don't weaken the integrity of conversion records.** A batch must never leave half-converted or wrongly converted records behind. It either converts every selected sale or none of them. (UC on C.2: "Batch is therefore all-or-nothing"; D.2, D.3.)

Business-why caveat: S1 doesn't say why batching matters to the user. G1 assumes the payoff is less repetitive per-sale work. That's a reasonable reading of "batch", but it isn't stated anywhere. See OQ5.

## 2. User needs

- **N1.** Select several unconverted sales in the same source currency and convert them in one step. (A, A.1, B.1)
- **N2.** Enter a single exchange rate, plus fee/tax, once, and have it applied to every selected sale. (UC on A.2)
- **N3.** Be stopped with a clear error, and have nothing converted, if the selection mixes source currencies. (UC on B/B.4)
- **N4.** Be stopped with a clear error, and have nothing converted, if the selection includes any ineligible sale (already converted, or already in the account currency). Deselect those sales and try again. (UC on B.6/A.4/C.2)
- **N5.** Get clear feedback on whether the batch succeeded or failed. Because the batch is all-or-nothing, the result is a single success or a single failure, not a per-sale breakdown. (C.1, UC on C.2)

## 3. Constraints

- **C1. Same source currency.** Every sale in a batch must have the same `currency_code`. The target is always the tenant's account currency, so it's uniform by construction. (B.1, B.2, UC)
- **C2. All-or-nothing.** Any blocking condition, whether mixed currencies, an ineligible sale, or a validation failure, means no sale is converted. (UC on B.4 and C.2)
- **C3. Eligibility.** Only unconverted sales whose source currency differs from the account currency are eligible. Already-converted sales and sales already in the account currency block the batch. (UC on B.6/A.4; A.4; B.6)
- **C4. Inherited behaviour stays in force.** Batch conversion must keep the existing single-sale rules: the account currency must be set (D.3), the amount and note validation limits apply to each resulting conversion (D.2), and the existing tests and arch test must still pass (D.4).
- **C5. No change to single-sale conversion.** The existing Convert and Edit conversion actions must behave exactly as they do today. (A.3, A.4, `EditSaleConversionAction` referenced in A.4)
- **C6.** No deadline, budget, or staffing constraint is stated in S1 or UC. The logistics in section 5 are PM-set ceilings, not inherited ones.

## 4. Scope

### In scope
- **S-in-1.** A way to select multiple sales from the Sales list and start one batch conversion. A bulk action on the Sales table is the presumed entry point. Architect confirms the mechanism. (A.1)
- **S-in-2.** A batch input with one shared exchange rate plus fee/tax, applied to every selected sale. (UC on A.2)
- **S-in-3.** Blocking checks before anything is converted:
  - mixed source currencies (UC on B.4)
  - already-converted sales in the selection (UC on A.4)
  - sales already in the account currency (UC on B.6)
  - account currency not set (D.3)
- **S-in-4.** All-or-nothing execution and a single success or failure notification. (UC on C.2; C.1)
- **S-in-5.** Regression and new-behaviour test coverage consistent with the existing `tests/Feature/Sales/` suite and arch test. (D.4)

### Out of scope (explicit)
- **S-out-1. Partial success.** No "convert the eligible ones and skip the rest" mode. (UC: block the whole batch)
- **S-out-2. Mixed-currency batches**, including grouping a mixed selection into per-currency sub-batches. (UC on B.4)
- **S-out-3. Batch re-conversion or batch editing** of already-converted sales. Corrections stay on the existing single Edit conversion action. (UC on A.4)
- **S-out-4. Batch conversion of sales already in the account currency.** (UC on B.6)
- **S-out-5. Per-row amounts in a batch**, or a total amount allocated across sales. Only a shared rate is supported. (UC on A.2, rejecting the alternatives listed in A.2/E.1)
- **S-out-6. Reading B.3** ("those" means some configured or previously used currency list). There is no evidence for it, and UC settled on the same-source-currency reading. (B.3)
- **S-out-7. Other entry points**, such as a dedicated batch page or a CSV import. (A.1 alternatives)
- **S-out-8. Changes to single-sale conversion**, the account-currency model, or exchange-rate sourcing. There is no automatic rate lookup; the user enters the rate. (C5; UC on A.2)

## 5. Logistics

- **Effort ceiling:** about 3 engineer-days end to end, including tests and review. If the design needs much more than that, it's a sign the scope has grown beyond this plan, and Tech Lead should bounce it.
- **Milestones:**
  1. **M1, design settled.** Architect resolves the entry point, the input model mapping (rate plus fee/tax onto the existing conversion fields), and the all-or-nothing mechanism. Target: day 0.5.
  2. **M2, batch action working end to end on the happy path.** Same-currency, eligible selection, all sales converted. Target: day 1.5.
  3. **M3, blocking rules and feedback complete.** Mixed currency, ineligible sales, missing account currency, validation failure, all with nothing converted. Target: day 2.25.
  4. **M4, tests green and review passed.** Existing Sales suite and arch test unchanged and passing. Target: day 3.
- **Skills needed:** Laravel/PHP backend work on the services layer and validation, Filament admin UI (table bulk actions, action forms, notifications), and Pest feature testing. No new external dependency is expected. If one becomes necessary, that's a scope change that needs approval (project rule: no dependency changes without approval).

## 6. Tier

**Tier: Standard**

Rationale:
- This is new user-facing capability, not a fix to existing code: there is no bulk action on the Sales table today (A.1).
- There are real design questions for Architect:
  - how a shared rate maps onto the existing per-sale conversion inputs, where the rate is currently derived as gross / original (A.2, A.3)
  - how to guarantee all-or-nothing behaviour across many records (C.2)
  - whether to reuse `SaleConversionService::convert()` per sale (A.3)
- It writes financial records (sale conversion amounts, fees, taxes) for many rows in one operation. That's higher stakes than a single-row edit.
- It isn't Major. It extends an existing feature inside one resource and introduces no new system, integration, or schema-level initiative. The intake identifies no schema change, migration, auth change, or public API change. If design finds a migration is needed, Standard is still the right tier.

## 7. Task list (product-level)

1. **T1. Multi-select entry point.** Let users select several sales on the Sales list and start a batch conversion. (A.1, S-in-1)
2. **T2. Batch conversion input.** Collect one exchange rate plus fee/tax for the whole batch. (UC on A.2, S-in-2)
3. **T3. Selection guardrails.** Reject the batch, with a clear message, for:
   - mixed source currencies
   - already-converted sales
   - sales already in the account currency
   - a missing account currency
   (UC, A.4, B.4, B.6, D.3, S-in-3)
4. **T4. Apply the conversion to every sale.** Each selected sale gets a conversion record that follows the existing validation rules, with all-or-nothing semantics. (A.3, D.2, C2, S-in-4)
5. **T5. Result feedback.** Show one clear success notification, or one failure notification that states the reason. (C.1, N5)
6. **T6. Test coverage.** Cover the new behaviour and its blocking cases, and keep the existing Sales tests and arch test passing. (D.4, S-in-5)

## 8. Open questions (carried forward; not resolved by PM)

- **OQ1. Fee/tax semantics under a shared rate.** UC says "a single exchange rate (plus fee/tax) applied to every selected sale". It doesn't say whether the fee/tax entered is:
  - (a) the same absolute amount charged on each sale
  - (b) a batch total split across the sales
  - (c) a percentage
  This affects what users see and what gets recorded per sale. Architect may propose a default, but it should be confirmed with the user before implementation. (A.2, UC)
- **OQ2. Sales with unknown currency (`currency_code` null).** UC doesn't address B.5. Two choices:
  - (a) exclude them from batches; they would block the batch like other ineligible sales
  - (b) allow a batch of unknown-currency sales where the user supplies the currency once
  The default proposal is (a), the conservative option consistent with "same currency" being checkable. It's flagged for user confirmation. (B.5, E.4)
- **OQ3. Permissions.** D.1/E.7 are unanswered. The working assumption is that the batch action uses the same authorization as single conversion (non-members only). Tech Lead should confirm this is acceptable. (D.1)
- **OQ4. Batch size limit.** No upper bound on the number of sales per batch is stated. Architect should state whether one is needed. (No source; S1 silent)
- **OQ5. Business driver.** S1 doesn't state why batching is needed: volume, time saved, or reconciliation against a single bank deposit. G1 assumes efficiency. If the real driver is reconciling against one deposit, OQ1 option (b) may be the right answer. (S1)
