# Intake: batch-sales-conversion (build 2026-10-11-batch-sales-conversion)

Sources (under `intake-sources/`):
- **S1** `requirement.md` - the entire raw requirement, one line: "support batch conversion of sales, but only with those same currencies"
- **Context (not in intake-sources; code read to ground the outline, not requirement input)**: the existing single-sale conversion feature from commit 4ede53c. Cited as `code:<path>`.

Note: S1 is one sentence. Every node below is either a quote-anchored reading of S1 or an observation about existing code. Nothing here comes from a human beyond that sentence.

## A. Batch conversion of sales (core capability)
- Label: Allow converting multiple sales in one operation rather than one at a time.
- Source: S1, phrase "support batch conversion of sales".
- Status: ambiguous (the UI shape and input model are unspecified; see A.1, A.2).
- A.1 Entry point
  - Label: Where batch conversion is triggered. Presumed a bulk action on the Sales table (multi-select).
  - Source: S1 "batch conversion"; code: `app/Filament/App/Resources/Sales/SaleResource.php` (table has `recordActions` only, no `toolbarActions`/bulk actions); `app/Filament/App/Resources/Sales/Actions/ConvertSaleAction.php` (single-record action).
  - Status: ambiguous (bulk action is an assumption; alternatives, such as a dedicated page or import, are not excluded by S1).
- A.2 Per-sale inputs in a batch
  - Label: What the user supplies for each sale. The single flow needs, per sale, `converted_amount` (required, net received), optional `fee_amount`, `tax_amount`, `conversion_note`, and `currency_code` only when the sale's currency is unknown.
  - Source: code: `app/Filament/App/Common/Schemas/SaleConversionForm.php`; `app/Services/Sales/SaleConversionValidator.php`; `app/Services/Sales/SaleConversionCalculator.php` (exchange rate is derived as gross / original amount per sale).
  - Status: ambiguous. S1 does not say whether the batch takes one shared figure set, one rate applied to every sale, per-row amounts, or a total that is allocated across sales. Not resolved here.
  - Related: A.1, B.1, E.1.
- A.3 Reuse of existing single-sale logic
  - Label: Whether each sale in the batch is converted through the existing `SaleConversionService::convert()` (validation, account currency guard, save action).
  - Source: code: `app/Services/SaleConversionService.php`, `app/Filament/App/Common/Actions/Sales/SaveSaleConversionAction.php`. S1 is silent.
  - Status: ambiguous (this is a design decision, not a requirement in S1).
- A.4 Which sales are eligible for selection
  - Label: Existing single action is visible only for unconverted sales (`! $record->isConverted()`) and disabled when the account currency is unset. Whether batch follows the same eligibility, or can also re-convert (the existing Edit conversion action covers corrections), is unspecified.
  - Source: code: `ConvertSaleAction.php` (`visible`, `disabled`, `tooltip`); `EditSaleConversionAction.php`. S1 silent.
  - Status: ambiguous.
  - Related: D.1, D.3.

## B. "Only with those same currencies" (restriction)
- Label: Batch conversion is restricted by currency.
- Source: S1, phrase "but only with those same currencies".
- Status: ambiguous. "those" has no antecedent in S1; the sentence does not name which currencies. Candidate readings below are NOT chosen.
- B.1 Reading 1: all selected sales share the same original (source) currency
  - Label: A batch is allowed only if every selected sale has the same `currency_code`.
  - Source: S1; code: `sales.currency_code` shown in `SaleResource.php` line 44 (description under amount).
  - Status: ambiguous (one of several plausible readings; caller's suggested interpretation).
  - Related: A.2, B.4.
- B.2 Reading 2: each sale's currency equals the account currency's pair already used (source currency same, target currency same)
  - Label: Batch allowed only when source currency is the same across sales AND the target is the same (target is always the account currency, `Filament::getTenant()->currency_code`, so target uniformity is implied by tenant scope).
  - Source: S1; code: `SaleConversionService::guardAccountCurrency()` (target = `$sale->account->currency_code`).
  - Status: ambiguous (differs from B.1 only if sales in one tenant can have different target currencies, which the code suggests they cannot).
- B.3 Reading 3: only sales whose currency matches a currency previously/already supported or configured
  - Label: "those" refers to some pre-existing set of currencies (for example currencies already converted before, or a configured list).
  - Source: S1 "those same currencies" (unsupported by any other source).
  - Status: ambiguous (speculative; no evidence in sources).
- B.4 Behaviour when the selection contains mixed currencies
  - Label: Not specified whether the action is hidden/disabled, rejects the whole batch with a message, or skips non-matching sales and converts the rest.
  - Source: none (S1 silent).
  - Status: ambiguous.
  - Related: B.1, D.2.
- B.5 Sales with unknown currency (`currency_code` null)
  - Label: The existing flow lets the user supply the currency per sale for sales with unknown currency. Unspecified whether unknown-currency sales may be in a batch, and whether they count as "the same" as each other.
  - Source: code: `SaleConversionForm.php` (Select `currency_code`, visible when null); `SaleConversionValidator.php` (`currency_code` required when sale currency null); ListSales/Resource show 'Currency unknown'.
  - Status: ambiguous.
  - Related: B.1, A.2.
- B.6 Sales already in the account currency
  - Label: In the single flow, when sale currency equals account currency, the form is prefilled with `converted_amount = amount`. Unspecified whether "same currencies" in a batch includes this case (source = target).
  - Source: code: `ConvertSaleAction.php` `fillForm`.
  - Status: ambiguous.

## C. Result and feedback
- C.1 Success/failure reporting
  - Label: Single flow sends a success notification "Sale converted" or a danger notification (from `SaleConversionException`). Batch reporting (counts, per-sale failures) unspecified.
  - Source: code: `ConvertSaleAction.php`. S1 silent.
  - Status: ambiguous.
- C.2 Atomicity
  - Label: Whether a batch is all-or-nothing or partial success is unspecified.
  - Source: none (S1 silent).
  - Status: ambiguous.
  - Related: B.4, C.1.

## D. Constraints inherited from the existing feature (observations, not new requirements)
- D.1 Permissions
  - Label: Single action is authorized only for non-members of the tenant (`! Auth::user()->isMember(Filament::getTenant())`).
  - Source: code: `ConvertSaleAction.php` `authorize`. S1 silent on whether batch has the same permission.
  - Status: ambiguous (presumably same, not stated).
- D.2 Validation limits
  - Label: Amount rules: `decimal:0,2`, `min:0.01` for net, `min:0` for fee/tax, `max:SaleConversion::MAX_AMOUNT`, and combined net+fee+tax must not exceed MAX_AMOUNT; note max 1000 chars.
  - Source: code: `SaleConversionValidator.php`, `SaleConversionForm.php` (form "mirrors" validator, validator is source of truth).
  - Status: clear (existing behaviour). Whether it applies per sale in a batch: see A.2.
- D.3 Account currency must be set
  - Label: Conversion is disabled and a tooltip shown when the tenant has no `currency_code`; service throws `missingAccountCurrency`. Also `accountCurrencyChanged` guard for already-converted sales.
  - Source: code: `ConvertSaleAction.php`; `SaleConversionService.php`.
  - Status: clear (existing behaviour).
- D.4 Tests existing
  - Label: Existing coverage in `tests/Feature/Sales/` (`SaleConversionServiceTest`, `SaleConversionValidatorTest`, `SaleConversionCalculatorTest`, `SaleResourceTest`, `SaveSaleConversionActionTest`, `SaleConversionArchTest`). Batch work will need to stay consistent with the arch test.
  - Source: code: `tests/Feature/Sales/*`.
  - Status: clear.

## E. Open questions summary (for the approval gate)
- E.1 What does the user enter in a batch (shared rate, per-row amounts, a total to allocate)? -> A.2
- E.2 Which meaning of "those same currencies"? -> B.1 / B.2 / B.3
- E.3 Mixed-currency selection: block, or skip? -> B.4
- E.4 Unknown-currency and same-as-account-currency sales in a batch? -> B.5, B.6
- E.5 Which sales are eligible (unconverted only, or re-conversion)? -> A.4
- E.6 All-or-nothing or partial success, and how is it reported? -> C.1, C.2
- E.7 Same permissions as single conversion? -> D.1

No reference to any source line is lost: S1 is a single sentence, fully quoted at the top and anchored in A and B.
