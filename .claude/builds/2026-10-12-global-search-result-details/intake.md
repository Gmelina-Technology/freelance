# Intake: global search result details

Build: 2026-10-12-global-search-result-details
Source: `intake-sources/requirement.md` (verbatim user requirement, single line). Code observations (OBS) come from reading the repo at branch `claude/global-search-result-details-8f9b29`; paths are relative to repo root. No application code was modified.

Statuses: clear / ambiguous / conflicting. IDs are permanent.

## A. Requirements

- **R-1** Improve the "details" shown for resources in Filament global search results.
  - Source: `intake-sources/requirement.md`, phrase "improve resources search details for global search".
  - Status: ambiguous. "Improve" is undefined: no stated problem, metric or example. Related: OBS-1, Q-1.
- **R-2** Decide what to include in each resource's search result.
  - Source: `intake-sources/requirement.md`, phrase "decide what to include in search result".
  - Status: ambiguous (explicitly delegated). The user gave no field list; the product-manager decides, per resource, using OBS-3..OBS-17 as candidates. Related: Q-2, Q-3, OBS-3..OBS-17.
  - R-2 has a sub-item per resource that has global search touchpoints:
    - **R-2.1** Currencies (admin panel). Related: OBS-3.
    - **R-2.2** Bank details. Related: OBS-4.
    - **R-2.3** Categories. Related: OBS-5.
    - **R-2.4** Invitations. Related: OBS-6.
    - **R-2.5** Members. Related: OBS-7.
    - **R-2.6** Units. Related: OBS-8.
    - **R-2.7** Clients. Related: OBS-9.
    - **R-2.8** Invoices. Related: OBS-10.
    - **R-2.9** Projects. Related: OBS-11.
    - **R-2.10** Quotes. Related: OBS-12.
    - **R-2.11** Sales. Related: OBS-13.
    - **R-2.12** Tasks (top-level, `App\Filament\App\Resources\Tasks`). Related: OBS-14.
    - **R-2.13** Tasks (nested under Projects). Related: OBS-15.
  - Status of R-2.1..R-2.13: ambiguous (content to be decided). R-2.11, R-2.12 and R-2.13 also carry extra open questions (Q-5, Q-6, Q-7).

## B. Observations: current state

- **OBS-1** No resource in `app/` defines `getGlobalSearchResultDetails()`, `getGloballySearchableAttributes()`, `getGlobalSearchEloquentQuery()`, `getGlobalSearchResultTitle()`, `getGlobalSearchResultActions()` or `getGlobalSearchResultUrl()`. No panel provider calls `globalSearch(...)`, and `tests/` has no global search tests (grep for `lobalSearch` in app/, tests/, config/ returned nothing). Every resource therefore relies on Filament defaults. Source: grep over `app/`, `tests/`, `config/`. Status: clear. Related: R-1.
  - Default behavior in Filament v4 (not verified in vendor code here, to be confirmed by downstream): searchable attributes fall back to `$recordTitleAttribute` only, details are empty, the result URL is the view page if one exists, otherwise the edit page, and a resource is only globally searchable if `$recordTitleAttribute` is set and it has a view or edit page that the user can access. Status: ambiguous until verified. Related: Q-4.
- **OBS-2** Multi-tenancy. The app panel uses tenancy: `app/Providers/Filament/AppPanelProvider.php:44` has `->tenant(Account::class, 'id', 'account')`. Account-scoped models use the `App\Traits\HasAccount` trait. Currency (admin panel) is not account-scoped (`app/Models/Currency.php`, no `HasAccount`). Filament scopes global search queries to the current tenant through the resource tenant ownership relationship. `MemberResource` sets `$tenantOwnershipRelationshipName = 'accounts'` because User belongs to many accounts. Status: clear. Related: OBS-7, Q-4.
- **OBS-3** CurrencyResource (`app/Filament/Admin/Resources/Currencies/CurrencyResource.php`, admin panel). Title attribute `code` (line 25). Columns and form: code, name, symbol, created_at, updated_at. Model `Currency` has a string primary key `code` and no timestamps override. Pages: `getPages()` is at line 74; I did not open it to confirm that it has an edit or view page. Candidate details: name, symbol. Status: clear (state). Related: R-2.1.
- **OBS-4** BankDetailsResource (`app/Filament/App/Clusters/Settings/Resources/BankDetails/BankDetailsResource.php`). Title `bank_name` (line 24). Model `BankDetail` fields: bank_address, bsb, swift_bic_code, account_name, account_number. Candidate details: account name, masked or last digits of account number, SWIFT/BIC. Sensitivity: financial identifiers, so whether to show them (full or masked) is a decision point. Related: R-2.2, Q-3.
- **OBS-5** CategoryResource (`.../Settings/Resources/Categories/CategoryResource.php`). Title `name` (line 28). Other field: `color`. Candidate details: color. Searching color is already a table search column. Related: R-2.3.
- **OBS-6** InvitationResource (`.../Settings/Resources/Invitations/InvitationResource.php`). Title `email` (line 24). Model `Invitation` fields: role (enum AccountRole, with label), invited_by_id (relation `invitedBy` to User), accepted_at, token. Candidate details: role label, pending or accepted state (`isPending()`), invited by (needs eager load). The token must not be shown or searchable. Related: R-2.4, Q-3.
- **OBS-7** MemberResource (`.../Settings/Resources/Members/MemberResource.php`). Model is `User`, not `Account`-owned directly. Title `name` (line 31). Tenant ownership via `accounts` relation. Role is tenant-specific and computed via `$record->getAccountRole(Filament::getTenant())` (line 47). Email is a table search column. Table query already eager loads `accounts` through `modifyQueryUsing` (line 39), which applies only to the table and not to the global search query. Candidate details: email, role in current account. Pages: only `index` (ManageMembers); there is no view or edit page. Related: R-2.5, OBS-2, Q-4.
- **OBS-8** UnitResource (`.../Settings/Resources/Units/UnitResource.php`). Title `name` (line 26). Only field: name. Nothing meaningful to add as detail. Pages: only index (`getPages()` line 51, not opened). Related: R-2.6, Q-4.
- **OBS-9** ClientResource (`app/Filament/App/Resources/Clients/ClientResource.php`). Title `name` (line 31). Model `Client` fields: email, phone, currency_code, pocs (array of points of contact). Relations: projects, quotes, invoices, tasks, currency. Pages: index, create, view, edit (view at line 61). Candidate details: email, phone, currency_code; possibly names inside `pocs`. Searchable attributes could be extended to email, phone and pocs. Related: R-2.7.
- **OBS-10** InvoiceResource (`app/Filament/App/Resources/Invoices/InvoiceResource.php`). Title `number` (line 29). Model `Invoice` fields: amount (decimal:2), status (InvoiceStatus: Draft, Sent, Paid, Overdue, Void), issued_at, due_date, notes. Relations: client, project, task, items. The invoice has no currency column of its own, so the currency would come from the client's `currency_code` (`Client` has `currency_code`) or from the account. Access is blocked for members (`canAccess`, line 33). Pages: view and edit exist (lines 75-76); edit allowed only for Draft (`canEdit`, line 38). Candidate details: client name, status label, amount (with currency), due date. Requires eager loading `client`. Candidate searchable additions: client name. Related: R-2.8, Q-8.
- **OBS-11** ProjectResource (`app/Filament/App/Resources/Projects/ProjectResource.php`). Title `name` (line 27). Model `Project` fields: description, status (ProjectStatus: BACKLOG, IN_PROGRESS, BLOCKED, COMPLETED), due_date. Relations: client, assignees, quotes, invoices, tasks. Pages: `view` route points to `ProjectTaskBoard` (`/{record}/tasks`, line 56), and `edit` exists. Candidate details: client name, status label, due date. Requires eager loading `client`. Related: R-2.9, OBS-15.
- **OBS-12** QuoteResource (`app/Filament/App/Resources/Quotes/QuoteResource.php`). Title `number` (line 28). Model `Quote` fields: invoice_ref, amount (decimal:2), status (QuoteStatus: Draft, Sent, Accepted, Declined, Expired, Void, Converted), issued_at, valid_until, accepted_at, notes. Relations: client, project, task, items. Members blocked (`canAccess`, line 34). Pages: view and edit exist (lines 71-72). Candidate details: client name, status label, amount, valid-until date. Requires eager loading `client`. Related: R-2.10, Q-8.
- **OBS-13** SaleResource (`app/Filament/App/Resources/Sales/SaleResource.php`). Title attribute is `id` (line 26), which is a database id and not user-meaningful; the table shows category, transaction_date, amount, currency_code, `reference_key` (the invoice number), and conversion status. Pages: only `index` (line 121); no view or edit page. Under Filament defaults a resource without a view or edit page is not globally searchable or has no result URL (to be confirmed), so Sales may currently return no useful results or none. Members blocked (`canAccess`, line 30). Table already eager loads `invoice` and `saleConversion` (line 89). The `reference_key` links to Invoice via `Sale::invoice()` (reference_key to invoices.number). Candidate searchable attributes: category, reference_key. Candidate details: category, transaction date, original amount plus currency_code, conversion status, net received. Related: R-2.11, Q-5.
- **OBS-14** TaskResource, top-level (`app/Filament/App/Resources/Tasks/TaskResource.php`). Title `title` (line 30). Navigation label is "My Tasks" for non-members and members alike via `getNavigationLabel()`. Model `Task` fields: description, status (TaskStatus: OPEN, IN_PROGRESS, REVIEW, COMPLETED), priority (TaskPriority: High, Medium, Low), billing_status (TaskBillingStatus), due_date, position. Relations: client, project, assignee (User), category, quoteItem, workLogs. Pages: index/create/edit; the `view` route is commented out (line 66) and `edit` is registered (line 67). Candidate details: project name, client name, status, priority, due date, assignee. Requires eager loading. Members use this resource as "My Tasks", which suggests results may need to be limited to the user's own tasks (see Q-6). Related: R-2.12, OBS-15, Q-6, Q-7.
- **OBS-15** TaskResource, nested (`app/Filament/App/Resources/Projects/Resources/Tasks/TaskResource.php`). Same `Task` model, `$parentResource = ProjectResource::class` (line 23), title `title` (line 25), pages include `edit` (line 48). The same model is therefore exposed by two resources, so a task may appear twice in global search, or the nested resource may not be globally searchable without parent record context (to be confirmed). Related: R-2.13, OBS-14, Q-7.
- **OBS-16** Out of the requirement's listed touchpoints but present: `app/Filament/App/Clusters/Settings/Resources/EmailTemplates/EmailTemplateResource.php` (has `getPages()`, line 87) was not in the caller's list and its title attribute was not found by grep (no `$recordTitleAttribute`). Recorded for completeness; whether it is in scope is for the product-manager. Status: ambiguous. Related: Q-9.
- **OBS-17** Eager loading: no resource defines a global search query. Any detail that reads a relation (client, project, assignee, invitedBy) will cause N+1 queries unless `getGlobalSearchEloquentQuery()` adds `with(...)`. The default global search limit is 50 results per resource (Filament default, not verified here). Related: R-2, Q-4.

## C. Open questions

- **Q-1** What problem prompted "improve"? Examples: results are bare titles, results are hard to tell apart, expected records are not found. Related: R-1.
- **Q-2** Is the scope one result-detail line per resource only, or also searchable attributes (OBS-9, OBS-10, OBS-13), result actions and result URLs? The requirement text says "search details" and "what to include in search result", which could cover all of them. Related: R-2.
- **Q-3** Sensitivity: should bank account numbers, SWIFT codes, invitation emails/roles and member emails appear in results? If so, in full or masked? Related: OBS-4, OBS-6, OBS-7.
- **Q-4** Is each resource currently globally searchable at all (needs a view or edit page and a title attribute)? Resources lacking a page: Sales, Members, Units, possibly Currencies, Categories, BankDetails, Invitations. Whether to add pages or accept that they stay unsearchable is a decision. Related: OBS-1, OBS-7, OBS-8, OBS-13.
- **Q-5** Sales: replace `id` as title with something meaningful (reference_key, category and date)? Sales has no view or edit page, so where should a result link go? Related: OBS-13, R-2.11.
- **Q-6** Tasks: should member users only see their own assigned tasks in global search (matching "My Tasks")? Related: OBS-14.
- **Q-7** Tasks: should both Task resources be globally searchable, or only one, to avoid duplicates? Related: OBS-14, OBS-15.
- **Q-8** Amount display for Invoices and Quotes: which currency symbol or code (client currency, account currency)? The models store no currency of their own. Related: OBS-10, OBS-12.
- **Q-9** Is EmailTemplateResource in scope? Related: OBS-16.
- **Q-10** Does the Admin panel (Currencies) have global search enabled and is it in scope alongside the tenant app panel? Related: OBS-3, R-2.1.

## D. Structurability

The requirement is structurable: R-1 and R-2 cover it, with R-2 split per resource, and all unknowns are recorded as Q-n. Nothing was dropped.
