---
description: Run the Agentic Requirement-to-Deploy Workflow end-to-end for one build, acting as the deterministic Orchestrator (see .claude/reference/orchestrator-router.md). Dispatches intake-clarifier, planner, design, assessor, implementer, and reviewer in order, enforces the tier routing and the three revision caps via a state file, and stops for human approval at the gate and at escalation.
argument-hint: <raw requirement text, or a path to a file/folder containing it>
---

# Run the Requirement-to-Deploy Pipeline

You are acting as the **Orchestrator** for this run — see `.claude/reference/orchestrator-router.md` for the full spec this command implements. That file says the Orchestrator "is deterministic logic, not an LLM" for a reason: the guardrails below only work if they hold every time, not "usually, unless it seems fine to skip one." Where this command's instructions and your own in-context judgment about what seems reasonable disagree, follow this command, not your judgment — that's the entire point of it existing.

## 0. Set up the build

- Requirement input: `$ARGUMENTS`
- Build id: `<YYYY-MM-DD>-<short-kebab-slug>`, slug derived from the requirement. Ask the user only if it's genuinely ambiguous — don't stall on this.
- Build folder: `.claude/builds/<build-id>/` — create it now. This lives under `.claude/` deliberately, alongside the agents/skills/reference material, so it's never mistaken for application source or migrations.
- State file: `.claude/builds/<build-id>/state.json` — initialize now:

```json
{
  "stage": "intake",
  "tier": null,
  "risk_tier": null,
  "design_assessor_count": 0,
  "planner_bounce_count": 0,
  "implementer_reviewer_count": 0,
  "steps_spent": 0,
  "budget_step_cap": 40
}
```

**Before every routing decision below, re-read `state.json`. After every transition, rewrite it.** These counters are not something to track in your own reasoning across turns — a compaction, a restart, or you simply being wrong about how many retries something has had must not be able to violate a cap. `state.json` is the only source of truth for stage/tier/counters; if your sense of the history ever disagrees with the file, the file wins. Every time you dispatch to any subagent, increment `steps_spent`; if it would exceed `budget_step_cap`, stop and go to **§7 Escalate** instead, independent of anything else going on.

## 1. Intake

Dispatch to the `intake-clarifier` subagent (Task tool) with the raw requirement (`$ARGUMENTS`, or the file/folder it points to). It writes `intake.md` and `intake-sources/` inside the build folder. If it reports the requirement is too ambiguous to structure at all, stop and ask the user — don't guess on its behalf. Set `stage: "planning"`.

## 2. Planner

Dispatch to `planner` with `intake.md`. It writes `plan.md`, which includes the tier classification (trivial / standard / major). Copy that tier into `state.json`'s `tier` field.

- Tier is **trivial** → set `stage: "assessment"`, skip straight to §4. Do not invoke Design.
- Tier is **standard** or **major** → set `stage: "design"`, go to §3.

## 3. Design (skip entirely if tier is trivial)

Dispatch to `design` with `plan.md` and `intake.md`. It writes `design.md`. Set `stage: "assessment"`, go to §4.

## 4. Assessor

Dispatch to `assessor` with `plan.md` plus, for standard/major tier, `design.md`. (Trivial tier has no `design.md` — Assessor evaluates `plan.md` directly, per its shrunk-review process.) It writes/updates `spec.md`, including the risk tier — copy that into `state.json`'s `risk_tier` field.

Route strictly by these rules, checking the relevant counter in `state.json` before acting on any of them:

- **Trivial tier, shrunk review passes** → go to §5.
- **Trivial tier, shrunk review can't be waved through** → a tier escalation, not a failure. Set `tier: "standard"` in `state.json` and go to §3 for Design's first invocation on this build. Do **not** increment any counter for this — it's a correction to the classification, not a retry.
- **Standard/major tier, passes** → go to §5.
- **Fails, root cause design-fixable, `design_assessor_count < 3`** → increment `design_assessor_count`, go back to §3 with the specific finding.
- **Fails, root cause budget/timeline/scope, `planner_bounce_count == 0`** → set `planner_bounce_count = 1`, go back to §2 with the specific finding. Do not touch `design_assessor_count` for this.
- **Fails, root cause budget/timeline/scope, `planner_bounce_count` already 1** → go to §7.
- **Fails, `design_assessor_count >= 3`** → go to §7, regardless of root cause.

## 5. Human Approval Gate

**Stop here and wait for the user's explicit approval before doing anything else — every time, no matter how clean the build looks.** Present: the tier, the risk tier, `spec.md`'s acceptance criteria, and (for standard/major tier) a short summary of `design.md`. You do not get to decide on your own judgment that it "looks fine enough" to proceed — that's exactly the decision this gate exists to keep out of your hands.

- **Rejected** → route back to §4 with the rejection reason; Assessor re-applies its root-cause logic to decide where that goes next.
- **Approved** → set `stage: "implementation"`, go to §6.

## 6. Implementer → Validation → Reviewer

- Dispatch to `implementer` with `design.md` + `spec.md` (standard/major tier) or `plan.md` + `spec.md` (trivial tier).
- **Validator** — deterministic, run yourself, do not delegate to a subagent: `vendor/bin/pint --dirty --format agent`, then `php artisan test --compact` (narrow with `--filter` if the build only touches part of the app). This is this project's actual Validator, per its own CLAUDE.md/Boost conventions.
  - Fails → increment `implementer_reviewer_count`. `< 3` → back to `implementer` with the failure. `>= 3` → §7.
- Dispatch to `reviewer` — a **separate** Task-tool call from the Implementer's, not a continuation of it — with `spec.md`'s acceptance criteria.
  - Fails, `implementer_reviewer_count < 3` → increment, back to `implementer` with the Reviewer's finding.
  - Fails, `implementer_reviewer_count >= 3` → §7.
  - Passes → set `stage: "deploy"`, go to §8.

## 7. Escalate

Dispatch to `escalation-liaison` with the current `state.json` and whatever findings triggered this. Stop. Do not attempt another automatic retry of anything after this, regardless of how minor the last failure looks — that judgment belongs to the human this hands off to, not to you.

## 8. Deploy → Reconciler

If `risk_tier` is `high`, stop for one more explicit human confirmation immediately before deploy, even though the build already passed the gate in §5 — this mirrors the spec's rule that high risk requires sign-off both before implementation and again before deploy. Once the user confirms deploy happened, dispatch to `reconciler` to merge `plan.md` / `design.md` / `spec.md` into the vault or project docs. Set `stage: "reconciliation"`, then mark the build folder's `status.md` as `merged`.

## Known gaps in this run

- The **Reference-Graph Checker** (stale `intake.md` ID detection) isn't automated yet — Reconciler is relying on your own re-reading rather than a real tool. Say so if it seems to matter for this build.
- Implementer and Reviewer run as separate Task-tool calls (separate context) but not necessarily separate underlying models — true model separation would need a `model:` field pinned in their agent frontmatter, which isn't set yet.
