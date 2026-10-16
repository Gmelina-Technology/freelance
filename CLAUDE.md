# Freelance Manager

Multi-tenant Laravel 13 + Filament 5 app for freelance clients, projects, tasks, quotes, invoices and sales.
See [docs/FEATURES.md](docs/FEATURES.md) for the full feature list and where each feature lives.

<laravel-boost-guidelines>
## Stack & versions

- PHP 8.4 (served by Laravel Herd at `https://[kebab-case-project-dir].test` — never start a server; use `get-absolute-url`).
- Confirm package versions before relying on an API: `composer show <vendor/package>` or `package.json`.
- Do not change dependencies or add new base folders without approval.

## Project rules & skills

- Before planning or editing, open `.ai/rules/index.md`, read every rule file whose globs cover the paths in scope, and `grep -rin 'keyword' .ai/rules`.
- Activate the matching skill (`filament-development`, `flowforge-development`, `mcp-development`, `tailwindcss-development`, `laravel-best-practices`, `testing-best-practices`) whenever working in that domain.

## Conventions

- Follow sibling files for structure, naming and approach; reuse existing components.
- Descriptive names (`isRegisteredForDiscounts`, not `discount()`).
- Curly braces on all control structures; constructor property promotion; explicit param and return types.
- PHPDoc (with array shapes) over inline comments.
- Use `php artisan make:* --no-interaction` to create files; create factories and seeders for new models.
- Prefer named routes and `route()`.
- Only create documentation files when asked.

## Boost MCP tools

- `search-docs` before changes that depend on Laravel ecosystem APIs (pass `packages`; use broad topic queries without package names).
- `database-schema` before migrations/models; `database-query` for read-only queries; `browser-logs` for frontend errors.

## Frontend

- If a change doesn't show or you see "Unable to locate file in Vite manifest", run `npm run build` or ask the user to run `composer run dev`.

## Testing (Pest)

- Add or update tests for behavior changes; copy/styling/layout changes don't need them.
- Create tests with `php artisan make:test --pest {Name}` (add `--unit` for unit tests); use model factories and their states.
- Run the narrowest set: `php artisan test --compact --filter=name` or a file path.
- Do not delete tests without approval. Ask the user to run the full suite once affected tests pass.

## Formatting

- After editing PHP files, run `vendor/bin/pint --dirty --format agent`.
</laravel-boost-guidelines>
