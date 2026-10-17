---
description: Lean coding-buddy flow — architect designs (optional), developer builds, tech-lead reviews the code for correctness, design and security via the code-review-checklist skill. Runs only related tests locally and leaves the full suite to the PR's CI. A lighter alternative to /run-pipeline for everyday changes.
argument-hint: [--no-design] <task description>
---

# Code buddy

A lean version of `/run-pipeline` (see `.claude/commands/run-pipeline.md`): no intake, plan, tiers, QA Engineer, Delivery Manager, or vault merge. Three agents, one retry counter, and you stay in the loop.

| Agent | Model | Does |
|-------|-------|------|
| `architect` | opus | Short design note — skipped with `--no-design` |
| `developer` | sonnet | Builds it, runs Pint and the related tests |
| `tech-lead` | opus | Reviews the diff: `code-review-checklist` skill (plus `code-review` when available) + security pass + related tests (its "Reviewing code changes" section). Never edits code. |

## 0. Set up

- Task: `$ARGUMENTS` (strip a leading `--no-design`).
- Build id: `<YYYY-MM-DD>-<short-kebab-slug>`. Folder: `.claude/builds/<build-id>/` (gitignored, never committed).
- `state.json`:

```json
{ "stage": "design", "review_round": 0, "max_review_rounds": 2, "risk": null, "pr": null }
```

Re-read `state.json` before every routing decision and rewrite it after every transition — it, not your memory, decides whether the cap is reached.

Append one line to `.claude/builds/<build-id>/audit.jsonl` after every subagent dispatch, in the same format as `/run-pipeline`'s audit log (`ts`, `step`, `stage`, `agent`, `total_tokens`, `tool_uses`, `duration_ms`, copied verbatim from the dispatch's `<usage>` block, `null` if missing).

## 1. Design (skip with `--no-design`)

Dispatch `architect` with the task. Ask for a **short** `design.md` — files to touch, data flow, schema/API changes, risks, build order — one page, not a full blueprint.

Then stop and ask via `AskUserQuestion` (short question, no recap): approve the design, or give feedback. Feedback → back to `architect` once with it, then ask again. This is the only approval before code.

## 2. Build

Set `stage: "build"`. Dispatch `developer` with the task and `design.md` (if any). Tell it to follow sibling-file conventions and `.ai/rules`, run `vendor/bin/pint --dirty --format agent`, and run **only the related tests** (`php artisan test --compact <files>` or `--filter`). Never the full suite.

## 3. Review

Set `stage: "review"`, increment `review_round`. Dispatch `tech-lead` — a separate Task-tool call, never a continuation of the developer — and point it at its "Reviewing code changes (`/code-buddy`)" section. It writes `review-<round>.md` with a verdict, findings, risk, and the tests it ran. Copy `risk` into `state.json`.

- **Pass** → §4.
- **Fail, `review_round < max_review_rounds`** → back to §2 with only the `blocker` / `should-fix` findings. The developer fixes those, nothing else.
- **Fail, cap reached** → stop. Ask via `AskUserQuestion` whether to take over, allow one more round, or drop it; point to `review-<round>.md` rather than pasting it.

## 4. PR → CI

Ask via `AskUserQuestion` before committing, pushing, or opening the PR. If `risk` is `high`, say so in the question. Commits end with a `Build: <build-id>` trailer; the PR body has a `Build: <build-id>` line. Record the PR number in `state.json`.

The PR's CI (`.github/workflows/test.yml`) runs the full test suite, Pint, and the security audit — that replaces a local full run. Bind the PR with the app's PR tools and offer Auto-fix so CI is watched for you; never poll CI yourself. A CI failure goes back to §2 with the failing test output as the finding, then through §3 again (same `review_round` cap).

## Interaction style

Every question to the user goes through `AskUserQuestion` with a short question and concise options. Keep text between steps to a line or two.
