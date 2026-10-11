# Plan: global search result details

Build: 2026-10-12-global-search-result-details
Author: product-manager
Inputs: `intake.md` (R-*, OBS-*, Q-* IDs cited throughout), `intake-sources/requirement.md`.
Extra fact checked by PM (not in intake): `app/Filament/App/Resources/Tasks/Tables/TasksTable.php` lines 30-37 limit the Tasks table to `assigned_user_id = Auth::id()` for members. That filter is table-only (`modifyQueryUsing`), so it does not apply to global search (consistent with OBS-1, OBS-7, OBS-17). Referred to below as **F-1**.

The user handed the content decisions to the team ("decide what to include in search result", R-2). Every open question Q-1..Q-10 is decided below. None goes back to the user.

---

## 1. Goals

- **G-1 (R-1): Results are identifiable at a glance.** Each global search result shows enough context (client, status, date, amount, where relevant) for the user to pick the right record without opening it. Today results are bare titles (OBS-1).
- **G-2 (R-1, R-2): Users find records by what they remember.** Users can find a record by the identifiers they actually use (client email/phone, invoice/quote client name), not only by the title attribute.
- **G-3 (R-2, Q-7, Q-4): Search shows signal, not noise.** Global search covers the core business records only. It doesn't return duplicates, records with no destination, or settings rows.
- **G-4 (R-2, Q-3, Q-6, F-1): Search reveals nothing the user couldn't already see.** A result never exposes a record or field the user can't see through the resource's own list. It never surfaces sensitive financial identifiers or tokens.

## 2. User needs

- **U-1:** An owner/admin running the business types a client, project, invoice or quote reference and lands on the right record in one step (OBS-9..OBS-12).
- **U-2:** A team member types a task name and sees only their own tasks, with enough context (project, status, due date) to choose one (OBS-14, F-1).
- **U-3:** A platform admin in the admin panel finds a currency by code or name (OBS-3).

## 3. Constraints

- **C-1:** Multi-tenant app panel. Results must stay scoped to the current tenant account (OBS-2).
- **C-2:** Existing access rules must carry over to search: members can't access Invoices, Quotes or Sales (OBS-10, OBS-12, OBS-13), and they only see their own tasks (F-1).
- **C-3:** Detail lines that read relations must not cause N+1 queries (OBS-17).
- **C-4:** No new resource pages are added just to make something searchable (decision on Q-4). No new dependencies (repo convention, CLAUDE.md).
- **C-5:** No deadline, budget or team constraint is stated in the requirement (`intake-sources/requirement.md`). The logistics in section 7 are PM-set.

## 4. Decisions on open questions

| Q | Decision | Rationale |
|---|----------|-----------|
| **Q-1** What problem prompted "improve"? | Treat the problem as: (a) results are bare titles with no way to tell them apart, (b) records can't be found by secondary identifiers, (c) noise and duplicates in the result list. | These follow directly from OBS-1 (no customization at all), OBS-14/15 (duplicate Tasks) and OBS-13 (`id` title). No other problem is evidenced. |
| **Q-2** Scope: details only, or more? | In scope: result **details**, **searchable attributes**, **inclusion/exclusion** per resource, the **global-search query** (tenant/visibility scoping and eager loading), and the **result title** where the current one is meaningless. Out of scope: result **actions**, custom **result URLs**, panel-level search config (key bindings, debounce, placement). | Details on their own don't meet G-2 or G-4. Actions and URLs add surface without a stated need, and Filament's default URL (view, otherwise edit) is fine for every resource we keep. |
| **Q-3** Sensitive data | Bank details are **excluded from global search entirely**. No account number, BSB or SWIFT/BIC appears in any result, masked or not. Invitation tokens are never searchable or shown. Invitations and Members are excluded too (see Q-4), so their emails and roles aren't surfaced through search. Client email/phone **are** shown and searchable. | Bank identifiers are financial data. Showing them in a quick-search dropdown (screen-share or shoulder-surf risk) is worse than any benefit, and the records are few and reachable through Settings. Client contact details are ordinary business data, already visible to anyone who can open the client. |
| **Q-4** Resources without view/edit pages | **Don't add pages.** Every Settings-cluster resource is explicitly opted out of global search: Bank details, Categories, Invitations, Members, Units, Email templates. Sales is opted out too (see Q-5). The opt-out is explicit, not left to Filament's page-detection defaults, so behavior is deterministic and tested. | Settings rows are small config lists, reached through Settings navigation. Mixed in with business records they're noise (G-3). Members and Units have no view/edit page (OBS-7, OBS-8), so they'd have no destination anyway. An explicit opt-out also means the build doesn't depend on the unverified default behavior in OBS-1. |
| **Q-5** Sales title `id` and no destination page | **Exclude Sales from global search.** Leave its `$recordTitleAttribute` as is for this build; it's not used in search once Sales is excluded. A meaningful Sale title (used in modals and elsewhere) is logged as a follow-up, not done here. | Sales has no view/edit page (OBS-13), so a result would have nowhere to go, and adding a page breaks C-4. Sales tied to invoices are already reachable: searching the invoice number finds the Invoice (OBS-13: `reference_key` = invoice number). Changing the title attribute affects UI outside search, which is outside this requirement. |
| **Q-6** Members and their own tasks | **Yes.** For members, task search results are limited to tasks assigned to them, the same rule the Tasks table applies (F-1). Owners/admins see all tasks in the tenant. | Without this, global search shows members task titles that their "My Tasks" list deliberately hides. That's a visibility leak (G-4, C-2), and fixing it is in scope as part of the global-search query. |
| **Q-7** Duplicate Task resources | **Only the top-level TaskResource** (`App\Filament\App\Resources\Tasks`) is globally searchable. The nested Project Tasks resource is explicitly opted out. | One result per task (G-3). The top-level resource is the one with member-aware behavior and a standalone edit route that needs no parent context (OBS-14, OBS-15). |
| **Q-8** Currency for Invoice/Quote amounts | Show the amount with the **client's `currency_code`** (e.g. `USD 1,250.00`). If the client has no currency code, show the amount with no currency. Don't fall back to an account currency. | Invoice and Quote have no currency of their own (OBS-10, OBS-12), and the client is the only currency source the intake identifies. The client is already eager-loaded for the "Client" detail, so this costs no extra query. |
| **Q-9** EmailTemplateResource | **In scope, but only to exclude it** from global search, as with the other Settings resources. | It belongs to the same Settings cluster and has no title attribute (OBS-16). Opting it out explicitly keeps the Settings policy consistent. |
| **Q-10** Admin panel / Currencies | **In scope.** Currencies get details and searchable attributes in the admin panel. If verification finds CurrencyResource has no view/edit page (OBS-3 left this unconfirmed), it **stays unsearchable** (no page added, per C-4) and R-2.1 is closed as "not applicable". | Low effort, and helpful to the platform admin (U-3). It doesn't justify new pages. |

## 5. Per-resource content (R-2.1..R-2.13)

"Searchable" = attributes matched by the global search term. "Details" = label/value lines under the title, in the order listed. A detail with no value is omitted, not shown blank. Enum values (status, role, priority) are shown by their human label. Dates use the app's existing short date format.

### Included in global search

| Resource (R-ID) | Title | Searchable attributes | Details shown |
|---|---|---|---|
| **Clients** (R-2.7, OBS-9) | `name` (unchanged) | name, email, phone | Email; Phone; Currency (`currency_code`) |
| **Projects** (R-2.9, OBS-11) | `name` (unchanged) | name, client name | Client; Status; Due (due_date) |
| **Invoices** (R-2.8, OBS-10) | `number` (unchanged) | number, client name | Client; Status; Amount (client currency code + amount, per Q-8); Due (due_date) |
| **Quotes** (R-2.10, OBS-12) | `number` (unchanged) | number, client name | Client; Status; Amount (per Q-8); Valid until (valid_until) |
| **Tasks, top-level** (R-2.12, OBS-14) | `title` (unchanged) | title, project name | Project; Status; Priority; Due (due_date); Assignee (shown to owners/admins only. Members only ever see their own tasks, so it's redundant for them) |
| **Currencies, admin panel** (R-2.1, OBS-3) | `code` (unchanged) | code, name | Name; Symbol (conditional on Q-10) |

Notes:
- Client `pocs` (points of contact) are **not** searched or shown. They're an array field, which makes search behavior uncertain, and no need for them is evidenced. Possible follow-up.
- Descriptions and notes (Project description, Invoice/Quote notes, Task description) are **not** searched. Free text would flood results with weak matches (G-3).
- Projects aren't restricted per member in this build. No member restriction on the Projects list was identified. If Architect finds one, rule G-4/C-2 applies: search must match the list's visibility.

### Excluded from global search (explicit opt-out)

| Resource (R-ID) | Reason |
|---|---|
| Bank details (R-2.2, OBS-4) | Sensitive financial identifiers (Q-3). Settings noise (Q-4). |
| Categories (R-2.3, OBS-5) | Settings config list. Noise (Q-4). |
| Invitations (R-2.4, OBS-6) | Settings. Contains emails and tokens. Noise (Q-3, Q-4). |
| Members (R-2.5, OBS-7) | Settings. No view/edit page (Q-4). |
| Units (R-2.6, OBS-8) | Settings. Name only, no page (Q-4). |
| Email templates (OBS-16) | Settings. No title attribute (Q-9). |
| Sales (R-2.11, OBS-13) | No destination page. `id` title. Invoice search covers the main lookup (Q-5). |
| Tasks, nested under Projects (R-2.13, OBS-15) | Duplicate of top-level Tasks (Q-7). |

## 6. Scope

**In scope**
- S-1: Result details, searchable attributes and (unchanged) titles for the six included resources in section 5.
- S-2: Explicit global-search opt-out for the eight excluded resources in section 5.
- S-3: Member-visibility parity for Task search (Q-6, F-1). Tenant scoping is preserved for every included resource (C-1).
- S-4: No N+1 queries for relation-based details (C-3, OBS-17).
- S-5: Automated tests covering the behavior above (none exist today, OBS-1).

**Out of scope (explicit non-goals)**
- N-1: Adding view or edit pages to any resource (Members, Units, Sales, Currencies, or others).
- N-2: Global search result actions, and custom result URLs.
- N-3: Changing Sales' `$recordTitleAttribute`, or the record title used anywhere outside global search (follow-up).
- N-4: Searching Client `pocs`, or descriptions/notes on any resource.
- N-5: Panel-level global search settings (key bindings, debounce, position, result limits), and any search engine or full-text indexing.
- N-6: Showing bank account numbers, BSB or SWIFT/BIC anywhere in search, masked or not.
- N-7: Changes to table search, list filters or any non-global-search UI.

## 7. Success criteria (product-level)

- SC-1: Searching a client's email or phone returns that client. The result shows email, phone and currency.
- SC-2: Searching a client name returns that client's projects, invoices and quotes (for users with access). Each result shows its client and status, and invoices and quotes also show amount with currency.
- SC-3: A task appears **once** in global search, never twice.
- SC-4: A member searching for a task title that's assigned to someone else gets no result for it. The same search by an owner/admin returns it.
- SC-5: Members get no Invoice, Quote or Sale results, as today.
- SC-6: No Settings-cluster record (bank details, categories, invitations, members, units, email templates) and no Sale ever appears in global search.
- SC-7: No result exposes bank identifiers or invitation tokens.
- SC-8: Results never cross tenants: a record from account A is never returned while working in account B.
- SC-9: A search returning many results with relation-based details doesn't issue a query per result.
- SC-10: Admin panel: searching a currency code or name returns the currency with name and symbol, or R-2.1 is documented as not applicable (Q-10).

## 8. Logistics

- **Timeline (milestones, PM-set; no deadline in the requirement, C-5):**
  - M1 (day 1): Architect confirms Filament v4 default behavior (OBS-1 sub-bullet, OBS-15, Q-10 page check) and settles the approach.
  - M2 (day 1-2): Included resources implemented, with tenant/member scoping and eager loading.
  - M3 (day 2): Exclusions implemented. Tests in place.
  - M4 (day 2-3): Tech Lead review, fixes, merge.
- **Effort ceiling:** about 2-3 engineer-days. If it runs past 4 days, re-scope: drop Currencies (R-2.1) first, then the extra searchable attributes, and keep the member-visibility fix (Q-6) and the exclusions.
- **Skills needed:** Laravel + Filament v4 resources (global search API, tenancy), Eloquent query scoping and eager loading, Pest feature testing with multi-tenant fixtures.

## 9. Task list (product-level)

1. T-1: Confirm baseline. Check which resources are globally searchable today and where results link (OBS-1, OBS-15, Q-10).
2. T-2: Clients: richer results and lookup by email/phone (R-2.7).
3. T-3: Projects: client, status and due date in results; findable by client name (R-2.9).
4. T-4: Invoices: client, status, amount with currency, due date; findable by client name (R-2.8, Q-8).
5. T-5: Quotes: client, status, amount with currency, valid-until; findable by client name (R-2.10, Q-8).
6. T-6: Tasks (top-level): project, status, priority, due date, assignee; findable by project name; members see only their own (R-2.12, Q-6).
7. T-7: Remove noise. Opt out the Settings resources, Sales and nested Tasks (R-2.2..R-2.6, R-2.11, R-2.13, OBS-16).
8. T-8: Currencies in the admin panel, if a destination page exists (R-2.1, Q-10).
9. T-9: Tests for SC-1..SC-10.

## 10. Remaining open items for downstream (not product decisions)

- OBS-1 sub-bullet: Filament v4 default searchability and URL rules haven't been verified in vendor code. Architect to confirm. The decisions above don't depend on the outcome, because exclusions are explicit.
- Q-10 page check for CurrencyResource. Outcome rule already set above.
- Follow-ups logged, not in this build: a meaningful Sale record title (N-3); searching Client `pocs` (N-4).

## 11. Tier

**Tier: standard**

Justification: This isn't trivial. It touches 14 resources across two panels, and it changes the global-search **queries**, not just display: it adds member-visibility scoping to Task search (closing an apparent visibility gap, F-1), eager loading, and tenant-scoping guarantees. That puts it in authorization/data-visibility territory, which the trivial-tier disqualifiers are meant to catch even when the change is small. No schema change, no data migration, no public API change, no new dependency, no new pages, and it's sized to a few days, so it isn't major. Architect has a real, if modest, decision to make: how to scope the global-search query consistently with existing list visibility, and how to opt out cleanly.
