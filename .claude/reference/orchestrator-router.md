---
name: orchestrator-router
description: Agent #7 of the Agentic Requirement-to-Deploy Workflow — deterministic control logic, not an LLM. This file is a control-flow spec, not a system prompt, and should be implemented as code (a state machine), not wired up as a model call.
tools: N/A — deterministic logic, not invoked as an LLM agent
---

## Why this file looks different from the others

Every other file in this directory is a system prompt for an LLM instance. This one isn't, on purpose — the spec is explicit that the Orchestrator/Router "is deterministic logic, not an LLM," because the whole point of the guardrails it enforces (bounded retries, budget caps, risk-tiered routing) is that they hold reliably every time, not "usually, unless the model reasons its way around them." Implement this as ordinary code: a state machine with explicit transitions, not a prompt.

## State it tracks, per build

- Current stage (`intake` → `planning` → `design` → `assessment` → `approval-gate` → `implementation` → `validation` → `review` → `deploy` → `reconciliation`).
- Design/Assessor revision count (resets when entering assessment fresh, not on every bounce).
- Planner-bounce count — separate counter from the Design/Assessor revision count. Capped at 1, not 2–3.
- Implementer/Reviewer revision count — separate again, resets on entering implementation fresh.
- Budget/step spend so far, against the build's configured cap.
- Risk tier, as set explicitly by the Assessor and confirmed at the approval gate — this doesn't change mid-build without going back through the gate.
- Tier (trivial / standard / major), as set by the Planner and confirmed or escalated by the Assessor. Trivial can escalate to standard mid-build; never the reverse, and never major down to standard/trivial.

Three independent counters, not one shared count — see Open Decision in the main spec about whether that's actually the right call once there's usage data; for now, implement them as independent.

## Transition rules

**Front of the pipeline (Planner → Design → Assessor):**
- **Planner completes, tier is trivial** → route directly to Assessor, skipping Design.
- **Planner completes, tier is standard or major** → route to Design, as normal.
- **Assessor evaluating a trivial-tier build finds something its shrunk review can't wave through** → this is a tier escalation, not a bounce. Set tier to standard, route to Design for its first invocation on this build, and continue via the standard front-of-pipeline rules below from there. Do not increment the Design/Assessor revision count or the Planner-bounce count for this — it's a correction to the tier, not a retry of anything.
- **Assessor evaluating a trivial-tier build passes its shrunk review** → route to the Human Approval Gate, same as a standard/major-tier pass.
- **Assessor fails, root cause is design-fixable, Design/Assessor revision count under cap (2–3)** → route back to Design with the Assessor's specific finding. Increment the Design/Assessor revision count.
- **Assessor fails, root cause is budget/timeline/scope, Planner-bounce count is 0** → route back to Planner with the Assessor's specific finding. Increment the Planner-bounce count to 1. Do NOT increment the Design/Assessor revision count for this — it's a different failure class with its own cap.
- **Assessor fails, root cause is budget/timeline/scope, Planner-bounce count already 1** → do not bounce again. Route to Escalation/Human-liaison.
- **Assessor fails, Design/Assessor revision count at cap** → route to Escalation/Human-liaison regardless of root cause.
- **Assessor passes** → route to the Human Approval Gate.
- **Human Approval Gate rejects** → route back to Assessor (not Design, not Planner — the Assessor decides from there whether the rejection reason is itself design-fixable or a planning issue, using the same root-cause logic above).
- **Human Approval Gate approves** → route to Implementer.

**Build/validate/review loop (unchanged in shape from before, different acceptance-criteria source):**
- **Validator fails** → route back to Implementer, increment the Implementer/Reviewer revision count. If that count is at cap: stop, route to Escalation/Human-liaison instead of another retry.
- **Reviewer fails, Implementer/Reviewer revision count under cap** → route back to Implementer with the Reviewer's specific findings (against `spec.md`'s acceptance criteria) attached. Increment the count.
- **Reviewer fails, count at cap** → route to Escalation/Human-liaison. Do not attempt another automatic retry regardless of how minor the last failure looked.
- **Reviewer passes** → route to deploy.
- **Deploy completes** → route to Reconciler for vault merge (`plan.md`, `design.md`, `spec.md`).

**Cross-cutting:**
- **Reference-Graph Checker flags a stale `intake.md` ID citation** → route to Reconciler. Do not attempt to auto-resolve this yourself even for what looks like a simple case — classifying "simple relabel" vs. "content change" is the Reconciler's judgment call, not deterministic logic the Orchestrator should approximate.
- **Assessor's risk tier is high** (auth, payments, data migrations, public APIs) → require the human approval gate before proceeding past assessment, and again before deploy, regardless of how the automatic checks came out.
- **Budget/step cap reached, any stage** → stop and route to Escalation/Human-liaison, independent of any other counter.

## What it logs

Every transition, every cap check (pass or trip), and every routing decision — timestamped, per build — for audit and, on client work, billing traceability. Log which counter incremented on every bounce (Design/Assessor, Planner-bounce, or Implementer/Reviewer) so a build that thrashes across multiple loop types is visible as such, not just as "a lot of retries." This log is what the Escalation/Human-liaison formats for a human and what feeds `deviation-log.md` alongside the Reconciler's own entries.

## What it explicitly does not do

- It does not evaluate the content of a plan, a design, a spec, an implementation, or a review — only their pass/fail/status signals, root-cause classifications, and the counts/caps around them.
- It does not decide whether an Assessor failure is design-fixable versus a planning problem — that classification comes from the Assessor itself, as part of its output; the Orchestrator just routes on it.
- It does not decide whether a stale reference is safe to auto-cascade — see Reconciler.
- It is not a place to add "smart" retry logic (e.g. skipping a cap because a failure "looks minor"). If that kind of judgment is wanted, it belongs in an LLM-backed agent's prompt, reviewed as such — not quietly folded into the deterministic router where it can't be inspected the same way.
