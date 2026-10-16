# Features

Freelance Manager is a multi-tenant Laravel + Filament app for running a small freelance operation:
clients, projects, tasks, quotes, invoices, sales and reports, with a REST API and an MCP server for AI agents.

## Accounts, auth & team

- **Panels** — App panel at `/app` (tenant-aware workspace), Admin panel at `/admin` (currencies).
- **Auth** — registration, login, email verification, password reset, required email MFA.
- **Multi-tenancy** — every record is scoped to an `Account` (`HasAccount` trait); users register or switch accounts.
- **Roles** — `Owner`, `Manager`, `Member` (`AccountRole`). Members only see their own tasks.
- **Capabilities** — `AccountCapability` gates features by role: manage invoices, quotes, sales, clients; look up clients; use the API.
- **Invitations** — invite members by email; signed acceptance link (`InvitationService`, `/invitations/accept/{token}`).
- **Account settings** (Settings cluster) — account profile, bank details, categories, units, email templates, members, invitations.

## Clients & projects

- **Clients** — CRUD, view page, per-client invoice list.
- **Projects** — CRUD with status (`Backlog`, `In progress`, `Blocked`, `Completed`).
- **Kanban task board** per project (Flowforge, drag-and-drop between statuses).

## Tasks

- Tasks with status (`Open`, `In progress`, `Review`, `Completed`), priority (`High`/`Medium`/`Low`), assignee, category and unit.
- **Billing status** — `Billable` → `Pending payment` → `Paid`, driven by invoices.
- **Work logs** per task.
- **Notify assignee** action.
- Logic centralised in `TaskService` (shared by Filament, API and MCP).

## Comments

- Threaded comments with replies, editing, deleting and emoji reactions (`CommentThread` Livewire component).
- **@mentions** with database notifications (`MentionParser`).
- File attachments with authenticated download.
- Attachable to any `Commentable` model (`HasComments` trait).

## Quotes

- Quotes with line items and generated unique numbers (`QuoteService`).
- Lifecycle: `Draft` → `Sent` → `Accepted` / `Declined` / `Expired` / `Void` / `Converted`.
- Send by email, PDF preview (`/quotes/{quote}/preview.pdf`).
- Editing a declined or expired quote reopens it as draft.
- **Accepting a quote generates one task per line item** (idempotent).

## Invoices

- Generate a draft invoice from a project's completed billable tasks, or build one by hand.
- Lifecycle: `Draft` → `Sent` → `Paid` / `Overdue` / `Void` (`InvoiceService`).
- Send by email (`InvoiceMailSent`), PDF rendering (laraveldaily/laravel-invoices).
- Marking paid settles the invoice's tasks and records a **sale**; voiding hands tasks back to billing.

## Sales & currencies

- A sale is recorded in the invoice's original currency and starts **unconverted**.
- **Sale conversion** into the account currency — single or batch at one shared rate (`SaleConversionService`).
- Only converted sales count in reports and revenue widgets.
- Currencies managed in the Admin panel.

## Reports & dashboard

- **Reports cluster** — Sales (by month / quarter / year charts), Client revenue (per client and project), Receivables (pending and overdue per client and project).
- **Dashboard widgets** — revenue stats, monthly sales chart, task stats, tasks by team, "my tasks" table.
- Global search across resources.

## Integrations

- **REST API** (`/api/tasks`) — list, create and update tasks; Sanctum bearer token, scoped to the token's account (`bind.account`).
- **MCP "Billing" server** (`/mcp`) — tools for tasks (list/get/create/update/complete/delete), quotes (list/get/create/update/send/accept/decline/void/PDF), invoices (list/get/create/generate/send/mark paid/void/PDF, billable tasks) and lookups (clients, projects, categories, units, project people).
- **API tokens page** — self-service Sanctum tokens with abilities `tasks:write`, `quotes:write`, `invoices:write` (no abilities = read-only).
- **CLI** — `php artisan freelance:api-token {email} {account}` issues a token.

## Other

- Email templates for invoice and quote requests.
- Terms and privacy pages (`/terms`, `/privacy`).
- Seeded demo account for local development (see README).
