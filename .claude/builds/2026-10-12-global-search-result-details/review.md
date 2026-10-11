# QA review: global search result details

Build: 2026-10-12-global-search-result-details (tier: standard, risk: HIGH)
Reviewer: qa-engineer
Pass: 1, 2026-10-12
Checked against: `spec.md` v1 (AC-1..AC-19, plus section 7 "Human gate"), with `design.md` used for intent.
Change set reviewed: the uncommitted working tree on `claude/global-search-result-details-8f9b29`. That's 17 modified files plus the untracked `app/Filament/App/Common/GlobalSearch/GlobalSearchDetails.php`, `tests/Feature/GlobalSearch/GlobalSearchResultsTest.php` and `tests/Feature/GlobalSearch/GlobalSearchVisibilityTest.php`.
Validator: Pint passed, and the full Pest suite passed 521/521 (orchestrator). I re-ran `tests/Feature/GlobalSearch` and `tests/Feature/TasksTableCompletedFilterTest.php`, and got 53 passed (91 assertions).

## Summary

The implementation is correct against every criterion I could check by code reading.
- The visibility scopes are correct.
- The table refactor keeps the old semantics.
- The 8 opt-outs are in place.
- Tenant scoping is kept.
- Eager loading is in place.

The build fails on test evidence. Two tests claim to check provider-level exclusion, and both are vacuous. The cause is how Pest negates a `toContain` call with more than one needle. These tests are the only Pest check of the "provider results contain no X category" clauses in AC-9 and AC-12. The fix touches the tests only.

## Per-criterion results

| AC | Result | Evidence |
|---|---|---|
| AC-1 Clients | PASS | `ClientResource::getGloballySearchableAttributes()` returns `['name','email','phone']`. Details are `Email`, `Phone`, `Currency`, in that order. Test `finds a client by email or phone...` covers both terms (dataset), with an exact `toBe` on details and a view URL. |
| AC-2 Projects | PASS | `ProjectResource` uses `['name','client.name']`, details `Client`/`Status`/`Due`. Test `finds a project by its client name` checks exact details, `Nov 03, 2026` and the `view` URL. |
| AC-3 Invoices | PASS | `InvoiceResource` details use `GlobalSearchDetails::money(amount, client?->currency_code)`. Tests cover `USD 1,250.00` and the bare `1,250.00` when there's no currency. |
| AC-4 Quotes | PASS | `QuoteResource` uses `Valid until`. Test `shows the quote amount and valid until date` checks the exact array. |
| AC-5 Tasks | PASS | `TaskResource` (top-level) uses `['title','project.name']` and the details in the spec's order. `Assignee` is null for members, so `filled()` drops the key. Tests cover the project-name search plus the edit URL plus exact details (owner), and the member case `not->toHaveKey('Assignee')`. Title search is covered by the visibility tests. |
| AC-6 Blank values | PASS | `GlobalSearchDetails::filled()` uses `array_filter` + `filled()`, which keeps key order. Test `leaves out client details that have no value` expects exactly `['Email' => ...]`. |
| AC-7 Titles/URLs unchanged | PASS | Code review: the diff has no `getGlobalSearchResultUrl()` / `getGlobalSearchResultActions()` overrides and no `$recordTitleAttribute` edits. Sales stays `'id'`. |
| AC-8 Member parity | PASS | `Task::scopeVisibleTo` / `Project::scopeVisibleTo`. Tests `shows members only the tasks assigned to them`, `shows members only the projects they are assigned to`, and `shows owners and managers every matching task and project` (dataset owner and manager). All three would fail without the scope. |
| AC-9 Role gates | **FAIL (test evidence)** | The `canGloballySearch()` part passes: the dataset `limits which resources each role can search` covers Invoices/Quotes/Clients, and the exclusions test covers Sales for every role. The clause "provider results for a member contain no Invoice, Quote, Sale or Client category" is tested by `returns no billing or client categories for members`, and that test is vacuous. See F-1. Code reading says the implementation meets the clause, but the assertion doesn't prove it. |
| AC-10 Table parity | PASS | `keeps the member task and project lists in line with search` uses OPEN task fixtures. Member sees A and not B, P1 and not P2. Owner sees all. `TasksTableCompletedFilterTest.php` passes. Code review of D-4 is under "D-4 refactor" below. |
| AC-11 Scope contract | PASS | Both scopes are `(Builder $query, User $user, Account $account): void`, with no `Auth`/`Filament` facade in the models. Only one top-level `where` / `whereHas` is added, with no `orWhere`. Task and Project `getGlobalSearchEloquentQuery()` start from `parent::getGlobalSearchEloquentQuery()`. |
| AC-12 Exclusions | **FAIL (test evidence)** | All 8 opt-outs are present (`protected static bool $isGloballySearchable = false;` in BankDetails, Category, EmailTemplate, Invitation, Member, Unit, Sale, nested Projects/Resources/Tasks). The test `does not search sales, settings or nested project tasks for any role` covers 8 resources x 3 roles, and it's sound. The provider-level test `never returns settings records or sales from a provider search` is vacuous (F-1). Bank identifiers and tokens: code review only. No included resource reads BankDetail or Invitation columns, and those two resources never run. No test asserts that account number, BSB, SWIFT/BIC or a token are absent (F-3, minor). |
| AC-13 No duplicates | PASS | `lists a task once, under the top-level tasks category` expects the category keys to be exactly `[TaskResource plural]` and the flattened count to be 1. |
| AC-14 Tenant isolation | PASS | `only returns records from the current account` creates the Bravo set in another account before binding, then asserts 5 titles with no "Bravo". A broken tenant scope would make that 10. Clients doesn't override the query, and the other four call `parent::`. |
| AC-15 No N+1 | PASS (minor gap) | `with(['client'])` on Projects/Invoices/Quotes and `with(['project','assignee'])` on Tasks. The dataset test uses 2 records with `preventLazyLoading()` and resets it in `afterEach`. Gap: "every relation-based detail is filled" is checked only for `Client`/`Project` on the first result, not `Assignee` (F-2). |
| AC-16 Bounded queries | PASS | Follows from AC-15 and the opt-outs. `isMember()` reads the cached `$user->accounts` relation (`User::getAccountRole`), so details add no per-row query. |
| AC-17 Rendered output | PASS | `renders result details in the global search component` runs `Livewire::test(GlobalSearch::class)` and asserts that `Zephyr Labs` and `USD 1,250.00` appear and `Zephyr Savings Bank` doesn't. |
| AC-18 Currencies | PASS (code), open at PR | `app/Filament/Admin/**` is unchanged. Recording R-2.1 as not applicable in the PR description can't be checked yet because no PR exists. |
| AC-19 Conventions | PASS (code), open at PR | The helper is at the required path (`final class`, static methods, return types, `@param`/`@return` array shapes on `filled()`). Pint passed. There are no composer, route or page changes. Whether `php artisan make:class` was used can't be verified from the output (I/O). Logging FND-1 in the PR description is open until a PR exists. |
| Section 7 Human gate | PASS | OQ-1: Currencies are unchanged. OQ-3: managers are treated as owners (scopes gate on `isMember` only, and the manager dataset is tested). OQ-2: FND-1 must be mentioned in the PR description, which is an open item for the PR step. |

## D-4 refactor (TasksTable / ProjectsTable): semantics preserved

- Tasks: the old code was `when(Auth::user()->isMember(Filament::getTenant()), fn => where('assigned_user_id', Auth::id()))`. The new code calls `visibleTo(Auth::user(), Filament::getTenant())`, which runs `if ($user->isMember($account)) where('assigned_user_id', $user->getKey())`. Same condition, same constraint (`Auth::id()` and `getKey()` are the same value). Non-members are unchanged.
- Projects: the old code was `whereHas('assignees', fn => wherekey(Auth::id()))`. The new code is `whereHas('assignees', fn => whereKey($user->getKey()))`. Same SQL.
- `User::isMember(Account $account)` was already non-nullable, so behavior with a null tenant is unchanged.
- The imports still in use (`Auth`, `Filament`, `Builder`) are still there. There are no other edits in either table file.
- Conditions (a) and (b) of the D-4 ruling are met.

## Findings

### F-1 (BLOCKING): the provider-level exclusion tests are vacuous
Files:
- `tests/Feature/GlobalSearch/GlobalSearchVisibilityTest.php`, test `returns no billing or client categories for members`
- the same file, test `never returns settings records or sales from a provider search`

Both tests assert this pattern:
```php
expect($keys)->not->toContain(A, B, C, D);
```
Pest's `OppositeExpectation::__call` (`vendor/pestphp/pest/src/Expectations/OppositeExpectation.php:770-784`) runs the positive `toContain(A, B, C, D)` and passes if it throws. `toContain` (`vendor/pestphp/pest/src/Mixins/Expectation.php:184-198`) asserts each needle in turn, so it throws as soon as **any one** needle is missing. The negated form therefore passes if **at least one** label is absent, not if all of them are.

I confirmed this with a standalone run of `expect(['Categories','Units','Bank details'])->not->toContain('Categories','Units','Bank details','Sales')`. The trace shows the first three needles pass `assertContains`, and only `'Sales'` fails. That failure is exactly what the negation treats as success.

Consequences:
- In both tests the Sales label is always absent. Sales is opted out, and a Sale's title attribute is its numeric id, which can never match "Zephyr". So both tests would still pass if Category, Unit or BankDetails were searchable again, or if Invoices, Quotes or Clients leaked to members.
- These are the only Pest checks of the provider-level clauses in AC-9 and AC-12. The FND-2 leak this build closes is exactly what the second test is supposed to guard.

Fix (tests only): assert each label on its own, for example `->not->toContain(A)->not->toContain(B)...`, or `expect(array_intersect($keys, [A, B, C, D]))->toBeEmpty()`.

Inference, not a test result: by code reading the implementation does satisfy both clauses. `DefaultGlobalSearchProvider` skips any resource whose `canGloballySearch()` is false, and the `canGloballySearch()` tests are sound. I expect the corrected assertions to pass without changes to application code.

### F-2 (minor, non-blocking): AC-15 "every relation-based detail is filled" is only partly asserted
The N+1 test checks only that the first result has a `Client` / `Project` key. For Tasks, the `Assignee` detail isn't asserted. `TaskFactory` assigns a `User::factory()` user who isn't in the account. The User tenant scope then makes the eager-loaded assignee `null`, so the detail is silently left out. The lazy-loading guard still covers the eager-load contract. Suggestion: in the tasks dataset row, assign a user who is in the account and assert `Assignee` is present.

### F-3 (minor, non-blocking): no explicit check that bank or token values are absent
AC-12 says no result may ever contain an account number, BSB, SWIFT/BIC or an invitation token. This holds by construction: BankDetails and Invitations never run, and no included resource reads those columns. But no test seeds those values and asserts they're absent. The Livewire test only checks `bank_name`. It's satisfied by code review. Adding assertions for those values in the Livewire test would be cheap hardening.

### F-4 (spec finding, for the Tech Lead): AC-12's provider clause can't be met with a single term as written
"A provider-level search for a term matching a Category, Unit, BankDetail **and Sale**" can't be done with one search. SaleResource's only searchable attribute is `id`, and Filament requires every search word to match. A term can't match a numeric id and a name like "Zephyr ..." at the same time. The Sale part is covered in practice by `canGloballySearch() === false` for all roles. If the Tech Lead wants it literally, it needs a separate provider search on the sale's id. I'm not reinterpreting the criterion here.

### Open at PR time (not code defects)
- The PR description must record R-2.1 as not applicable (AC-18) and log FND-1 as a follow-up (AC-19 and Human gate OQ-2).

## Required to pass

1. Fix F-1: rewrite the two multi-needle `not->toContain(...)` assertions so that each excluded category label is asserted absent on its own. Then re-run `tests/Feature/GlobalSearch`.

F-2 and F-3 are recommended but not required. F-4 is a question for the Tech Lead about the spec.

Round 1 verdict: FAIL (superseded by round 2 below)

---

## Round 2 (2026-10-12)

Scope: the developer's test-only changes for F-1, F-2 and F-3 in `tests/Feature/GlobalSearch/GlobalSearchVisibilityTest.php` and `tests/Feature/GlobalSearch/GlobalSearchResultsTest.php`.
Validator (orchestrator): Pint passed, and the full suite passed (523 passed, 0 failed). I re-ran `tests/Feature/GlobalSearch` and `tests/Feature/TasksTableCompletedFilterTest.php` myself and got 55 passed (151 assertions).

### No app code changed
- `git diff -- app` matches round 1: the same 17 files, 204 insertions and 16 deletions, and the same 220 changed lines.
- `GlobalSearchDetails.php` is the same 36-line file. Its mtime (00:24) is earlier than both test files (00:42).
- Only the two test files changed.

### F-1: RESOLVED. The assertions can now fail.
- **`returns no billing or client categories for members`:**
  - Now uses chained single-needle negations: `->not->toContain(Invoices)->not->toContain(Quotes)->not->toContain(Clients)`.
  - Each `not` wraps one needle. `OppositeExpectation` returns the original expectation, so each later `->not` is a fresh negation.
  - It adds a positive control: the owner's provider search for the same term **does** contain Invoices, Quotes and Clients. That proves the fixtures would match if the member gate leaked.
  - Sales now has its own provider search on the sale's id, which also handles F-4.
- **`never returns settings records or sales from a provider search`:**
  - Now runs as owner, manager and member.
  - It seeds a matching record for Category, Unit, BankDetail, Invitation (email `zephyr-invitee@...`) and Member (user "Zephyr Person"), and pre-asserts that each one exists.
  - It then asserts each excluded label is absent, one needle at a time, in a loop.
  - Sales is checked with a separate search on its id.
  - EmailTemplate is included too. It has no title attribute, so it can't be searched no matter what. Its line is accurate but can never fail, and that's harmless.
- **Sanity check outside the repo:** I ran a scratch script in my scratchpad (`negation.php`, `negation2.php`), with a PHPUnit configuration registered so Pest's failure path behaves the way it does in the runner.
  - Round-1 pattern: `expect(['Categories','Units','Bank details'])->not->toContain('Categories','Units','Bank details','Sales')` **passed**, even though three labels had leaked. That confirms the original F-1 was vacuous.
  - Round-2 chained pattern: it **fails** when the leaked label is first, in the middle or last, and passes when nothing leaks.
  - Round-2 loop pattern: it **fails** when Categories leaks and passes when nothing leaks.

### F-2: RESOLVED
In the N+1 test, the tasks dataset row now assigns `makeAccountUser($this->account, AccountRole::Member)`, a user who is in the account, so the tenant-scoped `assignee` eager load returns a user. The row also asserts `toHaveKey('Assignee')` while `preventLazyLoading()` is on. This would fail if the assignee eager load were dropped or returned null.

### F-3: RESOLVED
The settings and provider test seeds BankDetail `bsb` `987-654`, `swift_bic_code` `ZEPHAU2X` and `account_number` `5550001234`, plus an invitation `token` `zephyr-secret-invite-token`. For every role it serializes every result's title and details and asserts that none of those four values appears.
- Observation (not a defect): even if BankDetails were searchable again, its default details are empty and its title is `bank_name`. So this assertion only guards against a future change that surfaces those columns in a title or details. The opt-out itself is guarded by the per-label check above. That matches what AC-12 asks for: no result ever contains these values.

### F-4 (spec): addressed in the tests
A provider search on the sale's id now checks Sales directly, for members (AC-9) and for all three roles (AC-12). The single-term wording of AC-12 can still be clarified in spec v2, but no build action is needed.

### Updated results
| AC | Round 2 result |
|---|---|
| AC-9 Role gates | PASS. `canGloballySearch()` dataset, plus the non-vacuous provider check for members with a positive owner control. |
| AC-12 Exclusions | PASS. Opt-outs for 8 resources x 3 roles, a non-vacuous provider check per label and role, a Sale check by id, and an assertion that the bank and token values are absent. |
| AC-15 No N+1 | PASS. `Assignee` is now asserted as present under lazy-loading prevention. |
| All other ACs | Unchanged from round 1 (PASS). No app code changed. |

### Still open at the PR step (not code defects)
- The PR description must record R-2.1 as not applicable (AC-18) and log FND-1 as a follow-up (AC-19 and Human gate OQ-2). The orchestrator should check this when the PR is created.

Verdict: PASS
