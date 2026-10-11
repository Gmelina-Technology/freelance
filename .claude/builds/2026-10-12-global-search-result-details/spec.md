# Spec: global search result details

Build: 2026-10-12-global-search-result-details (tier: standard)
Author: tech-lead
Version: v1 (first assessment)
Inputs: `plan.md`, `design.md`, `intake.md`. Code read at branch `claude/global-search-result-details-8f9b29`, plus the vendor `filament/filament` v5.10.1 `HasGlobalSearch` trait. No application code was changed.

Verdict: PASS

Risk tier: high

---

## 0. What I verified independently

- `vendor/filament/filament/src/Resources/Resource/Concerns/HasGlobalSearch.php`: `canGloballySearch()` = `$isGloballySearchable && count(attributes) && canAccess()`, with no page check. `getGlobalSearchResultUrl()` returns `null` for nested resources, then tries view, then edit, then the index with `tableAction`. `getGlobalSearchEloquentQuery()` = `getEloquentQuery()`. This matches design 0.2 and 0.4b.
- `TasksTable.php:30-37` and `ProjectsTable.php:18-25`: the member rules match design 0.5 exactly.
- `canAccess()`: Clients allow owners only. Invoices, Quotes and Sales block members. No Settings-cluster resource overrides `canAccess()`, and `SettingsCluster::canAccess()` is the only member gate. So FND-2 (members can currently see Settings results) is plausible from the code.
- `CurrencyResource::getPages()` registers `index` only. Its table has `EditAction`/`DeleteAction` but no `ViewAction` (D-2 is accurate).
- Convention checks: local scopes exist (`Task::scopeBillable`, `Invoice::scopeOrderByStatusThenDueDate`). `app/Filament/App/Common/*` is an existing shared folder. `.ai/rules/models.md` (enum columns are strings) isn't affected. No other rule file matches the paths in scope.

## 1. Five-dimension verdict

| # | Dimension | Verdict | Reason |
|---|---|---|---|
| 1 | Feasibility | PASS | No schema, page, route or dependency changes. About 17 small file edits plus two test files, on the verified v5.10.1 API. That fits the 2-3 engineer-day ceiling (plan section 8). The v4/v5 mismatch (D-1) doesn't affect the API used. |
| 2 | Scalability | PASS | Each search queries a resource only if `canGloballySearch()` passes. Each query is tenant-scoped by a model global scope and capped at `LIMIT 50`. Query count is bounded at about 10 per search, whatever the data volume. At 10x data, the leading-wildcard `LIKE` and `EXISTS` subqueries grow linearly, but only within one tenant. That's the same cost profile as today's table search, and acceptable at this app's per-tenant size. Indexing or full-text search is excluded (N-5). |
| 3 | Maintainability | PASS | One visibility rule per model (`visibleTo` scope), shared by the list table and global search, so the two can't drift. The scope takes `User` and `Account` explicitly, so there are no facades in the model. Formatting rules (omit blank values, date format, `CODE amount`) live in one helper. Opt-outs are explicit properties, not left to framework defaults. |
| 4 | Security | PASS | Every entry point is gated. Resource-level `canAccess()` runs before any query. Row-level filtering uses the tenant global scope (which also covers `whereHas` subqueries and eager loads) plus `visibleTo` for Tasks and Projects. The Assignee detail is shown to non-members only. Bank details, invitations (emails, tokens) and members are never queried. This build closes two existing leaks: members seeing other users' tasks and unassigned projects (F-1, D-3), and members seeing Settings rows (FND-2). Remaining gap: FND-1 (direct-URL access). It exists today, this build doesn't introduce or widen it, and it's ruled out of scope below. |
| 5 | Performance | PASS | Relation-based details are eager-loaded (`client`, `project`, `assignee`), so there's no N+1. The nested Tasks opt-out removes a wasted query of up to 50 rows per search. `isMember()` reads the authenticated user's already-loaded `accounts` relation, so it adds no per-row query. |

## 2. Rulings on deviations and findings

- **D-1 (Filament v5.10.1, not v4): Accepted.** This is a factual correction with no scope impact. AC wording below uses v5 behavior.
- **D-2 (Currencies unchanged): Accepted for this build.** There's no view/edit page, so the plan's outcome rule (Q-10) applies: R-2.1 is not applicable and SC-10 is met by documenting that. The rule's premise ("stays unsearchable") turned out to be false. But the plan didn't ask for an opt-out, and opting out would remove the admin panel's only search field, a regression nobody approved. Leaving it unchanged is the only option that neither adds unapproved scope nor regresses current behavior. Currency is non-tenant reference data in the admin panel, so its current searchability has no security impact. Whether to add the roughly 15-minute `code`/`name` attributes and `Name`/`Symbol` details anyway is a scope choice for the human (OQ-1). I won't make that call.
- **D-3 (Project member scope in search): Accepted, and required.** `plan.md` section 5 pre-authorizes it ("If Architect finds one, rule G-4/C-2 applies"), and G-4/C-2 would fail without it. It's not a scope change. Locked in by AC-8.
- **D-4 (TasksTable/ProjectsTable refactored to the shared scope): Accepted, with conditions.** N-7 excludes changes to table search, list filters or other non-global-search *UI*. A pure extraction with identical SQL and identical visible rows is not a UI change. It's what stops the two visibility rules from drifting, and drift is the maintainability risk here. Conditions: (a) semantics are exactly preserved: member detection via `isMember(current tenant)`, Tasks `assigned_user_id = user key`, Projects `whereHas('assignees', whereKey(user key))`, non-members unchanged; (b) table parity tests (AC-10) and the existing `tests/Feature/TasksTableCompletedFilterTest.php` pass. If either condition can't be met, use the design's fallback: leave the tables untouched and call the scopes from the resources only.
- **FND-1 (direct-URL access gap): Out of scope, confirmed. Must be logged as a follow-up.** The gap is in `getEloquentQuery()` / route binding. It exists today, and this build doesn't make it worse: search becomes *stricter* than routes, not looser. Fixing it would change non-search behavior (edit/view binding, relation selects), which goes beyond S-1..S-5 and N-7, and it needs its own design and tests. Because it's an authorization gap, I'm raising it at the gate (OQ-2) so a human can decide its priority. It must not be silently dropped.
- **FND-2 (existing Settings leak to members): Accepted.** The S-2 opt-outs close it. Exclusion tests must cover member and manager as well as owner (AC-12).
- **FND-3 (intake default assumption wrong): Noted.** No action needed. SC-6 was failing before this build, so AC-12 is a real regression guard.

## 3. Acceptance criteria

All criteria are checked by Pest feature tests under `tests/Feature/GlobalSearch/` unless noted. Tests bind the panel and tenant with `bindFilamentTenant()`. They use pivot roles via `makeAccountUser()`, and create other-tenant records before binding.

### Content (S-1)
- **AC-1 (Clients, SC-1):** As owner, searching a client's email returns that client, and so does searching its phone. Details are exactly, in order, `Email`, `Phone`, `Currency` (the `currency_code`). The result URL is the client view page.
- **AC-2 (Projects, SC-2):** Searching a client's name returns that client's projects. Details are `Client`, `Status` (enum label), `Due` (`M d, Y`). The URL is the project `view` page.
- **AC-3 (Invoices, SC-2, Q-8):** Searching a client's name returns that client's invoices. Details are `Client`, `Status` (label), `Amount`, `Due`. `Amount` is `USD 1,250.00` for amount 1250 with client currency USD. It's `1,250.00`, with no currency and no account-currency fallback, when the client has no currency code.
- **AC-4 (Quotes, SC-2, Q-8):** Same as AC-3 for quotes, with `Valid until` in place of `Due`.
- **AC-5 (Tasks, R-2.12):** Searching a task's title, or its project's name, returns the task from the top-level `TaskResource`. Details are `Project`, `Status`, `Priority`, `Due`, and `Assignee` for owners and managers. A member's results never contain an `Assignee` key. The URL is the top-level task edit page.
- **AC-6 (Blank values):** Any detail whose value is blank (null or empty) is left out of the details array, not shown with an empty value. Remaining details keep their order. Covered by at least one test (for example, a client without phone or currency).
- **AC-7 (Titles and URLs unchanged, N-2, N-3):** No resource overrides `getGlobalSearchResultUrl()` or `getGlobalSearchResultActions()`. No `$recordTitleAttribute` changes, including Sales. Checked by code review.

### Visibility and access (S-2, S-3, G-4, C-1, C-2)
- **AC-8 (Member parity, SC-4, D-3):** A member searching a term that matches task A (assigned to them) and task B (assigned to someone else) gets only A. An owner and a manager get both. Likewise, a member gets only the projects they're in `assignees` for, while owner and manager get every matching project.
- **AC-9 (Role gates, SC-5):** For a member, `canGloballySearch()` is false for Invoices, Quotes and Sales. For anyone but an owner, Clients' `canGloballySearch()` is false. Provider results for a member contain no Invoice, Quote, Sale or Client category.
- **AC-10 (Table parity, D-4):** For a member, the Tasks list shows A and not B, and the Projects list shows P1 and not P2. Use non-completed task fixtures. For an owner, both lists show every record. The existing `TasksTableCompletedFilterTest.php` still passes. The table files contain no other behavior change (checked by code review).
- **AC-11 (Scope contract):** `Task::scopeVisibleTo()` and `Project::scopeVisibleTo()` take `(Builder $query, User $user, Account $account): void`. They don't read `Auth` or `Filament` facades, and they add only top-level `AND` constraints (no top-level `orWhere`). `getGlobalSearchEloquentQuery()` for Tasks and Projects builds on `parent::getGlobalSearchEloquentQuery()`. Checked by code review plus AC-8.
- **AC-12 (Exclusions, SC-6, SC-7, FND-2):** For each of the 8 excluded resources (BankDetails, Categories, Invitations, Members, Units, EmailTemplates, Sales, nested Project Tasks), `canGloballySearch()` is false for **owner, manager and member**. A provider-level search for a term matching a Category, Unit, BankDetail and Sale returns none of those categories. No result's title or details ever contains a bank account number, BSB, SWIFT/BIC or an invitation token.
- **AC-13 (No duplicates, SC-3):** A task inside a project appears exactly once across all provider categories, under the top-level `TaskResource` plural label.
- **AC-14 (Tenant isolation, SC-8):** Accounts A and B each have a client, project, invoice, quote and task matching the same term. As A's owner, every provider category contains only A's records. Included resources call `parent::getGlobalSearchEloquentQuery()` (or don't override it), so the tenant global scope still applies.

### Performance (S-4)
- **AC-15 (No N+1, SC-9, C-3):** With `Model::preventLazyLoading()` on and at least 2 matching records each, `getGlobalSearchResults()` for Projects, Invoices, Quotes and Tasks throws no lazy-loading exception, and every relation-based detail is filled. Lazy-loading prevention is reset after the test.
- **AC-16 (Bounded queries):** Global search adds no query whose count grows with the number of results (follows from AC-15). The nested Tasks resource runs no query (follows from AC-12).

### End-to-end and hygiene
- **AC-17 (Rendered output):** A Livewire test of `Filament\Livewire\GlobalSearch` with a search term shows a matching client/invoice title and a formatted amount (for example `USD 1,250.00`), and doesn't show a bank detail's name.
- **AC-18 (Currencies, D-2, SC-10):** `CurrencyResource` is unchanged in this build, and R-2.1 is recorded as not applicable in the PR description. If the human accepts OQ-1, this AC is replaced in spec v2.
- **AC-19 (Conventions):** The new helper lives at `app/Filament/App/Common/GlobalSearch/GlobalSearchDetails.php` and is created with `php artisan make:class`. All new or changed methods have explicit return types and PHPDoc array shapes. Pint has run (`vendor/bin/pint --dirty --format agent`). There are no new dependencies, pages or routes (C-4, N-1). FND-1 is logged as a follow-up item in the PR description.

## 4. Risk tier

Risk tier: high

Reason: the build changes **authorization-relevant row filtering**. It adds member-visibility scoping to Task and Project search, and it refactors the existing member filters on the Tasks and Projects lists (D-4). A mistake in either one widens what members can see. That's the auth/authz disqualifier, regardless of the small size or the clean scores above. No payments, data migrations or public APIs are touched. Human sign-off required.

## 5. Open questions for the human gate

- **OQ-1 (Currencies, D-2):** Currencies stay searchable by `code` only, with no details, and land on the list page. Do you want `code`/`name` attributes and `Name`/`Symbol` details added (about 15 minutes, no page needed, U-3), or should it stay unchanged as the plan's rule implies? The default is unchanged (AC-18).
- **OQ-2 (FND-1 priority):** Members can probably open another user's task, or a project they aren't assigned to, by direct URL. That was true before this build. Confirm it goes in as a separate follow-up, and decide how urgent it is.
- **OQ-3 (Manager visibility):** Managers are treated like owners for Tasks and Projects (all records, Assignee shown) but can't see Clients. This matches today's list behavior and `canAccess()`. Confirm that's intended, since the plan only names "owners/admins" and "members".

## 6. Change log

- v1 (2026-10-12): First assessment. PASS.

## 7. Human gate (2026-10-12)

- Approved.
- OQ-1: Currencies are left unchanged (AC-18 stands).
- OQ-2: FND-1 has been spun off as a separate background task. Still mention it in the PR description.
- OQ-3: Managers get the same access as owners for Tasks and Projects (confirmed).
