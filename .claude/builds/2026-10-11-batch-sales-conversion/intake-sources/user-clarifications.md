# User clarifications (2026-10-11, via orchestrator, answering intake.md ambiguities)

- A.2 Batch input: **one shared rate** — the user enters a single exchange rate (plus fee/tax) applied to every selected sale.
- B / B.4 Mixed currencies: selection must share the same source currency; if not, **block the action** (error notification, convert nothing).
- B.6 / A.4 / C.2 Ineligible sales (already converted, or already in account currency) in the selection: **block the whole batch** until they are deselected. Batch is therefore all-or-nothing.

# Approval gate (2026-10-11)

User approved with design defaults: OQ1 (a) same absolute fee/tax per sale; OQ2 unknown-currency blocks batch; OQ3 same auth as single conversion; OQ4 cap 100.
