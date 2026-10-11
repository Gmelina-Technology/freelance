---
name: orchestrator-router
description: Agent #7 of the Agentic Requirement-to-Deploy Workflow — deterministic control logic, not an LLM. This file is a control-flow spec, not a system prompt, and should be implemented as code (a state machine), not wired up as a model call.
tools: N/A — deterministic logic, not invoked as an LLM agent
---

## Why this file looks different from the others

Every other file in this directory is a system prompt for an LLM instance. This one isn't, on purpose — the spec is explicit that the Orchestrator/Router "is deterministic logic, not an LLM," because the whole point of the guardrails it enforces (bounded retries, budget caps, risk-tiered routing) is that they hold reliably every time, not "usually, unless the model reasons its way around them." Implement this as ordinary code: a state machine with explicit transitions, not a prompt.

## State it tracks, per build

- Current stage (`intake` → `planning` → `design` → `assessment` → `approval-gate` → `implementation` → `validation` → `review` → `deploy` → `reconciliation`).
- Architect/Tech Lead revision count (resets when entering assessment fresh, not on every bounce).
- Product Manager bounce count — separate counter from the Architect/Tech Lead revision count. Capped at 1, not 2–3.
- Developer/QA Engineer revision count — separate again, resets on entering implementation fresh.
- PR number, PR review round count (capped at 3), and the ids of review comments already handled.
- Budget/step spend so far, against the build's configured cap.
- Risk tier, as set explicitly by the Tech Lead and confirmed at the approval gate — this doesn't change mid-build without going back through the gate.
- Tier (trivial / standard / major), as set by the Product Manager and confirmed or escalated by the Tech Lead. Trivial can escalate to standard mid-build; never the reverse, and never major down to standard/trivial.

Three independent counters, not one shared count — see Open Decision in the main spec about whether that's actually the right call once there's usage data; for now, implement them as independent.

## Transition rules

**Front of the pipeline (Product Manager → Architect → Tech Lead):**
- **Product Manager completes, tier is trivial** → route directly to Tech Lead, skipping Architect.
- **Product Manager completes, tier is standard or major** → route to Architect, as normal.
- **Tech Lead evaluating a trivial-tier build finds something its shrunk review can't wave through** → this is a tier escalation, not a bounce. Set tier to standard, route to Architect for its first invocation on this build, and continue via the standard front-of-pipeline rules below from there. Do not increment the Architect/Tech Lead revision count or the Product Manager bounce count for this — it's a correction to the tier, not a retry of anything.
- **Tech Lead evaluating a trivial-tier build passes its shrunk review** → route to the Human Approval Gate, same as a standard/major-tier pass.
- **Tech Lead fails, root cause is design-fixable, Architect/Tech Lead revision count under cap (2–3)** → route back to Architect with the Tech Lead's specific finding. Increment the Architect/Tech Lead revision count.
- **Tech Lead fails, root cause is budget/timeline/scope, Product Manager bounce count is 0** → route back to Product Manager with the Tech Lead's specific finding. Increment the Product Manager bounce count to 1. Do NOT increment the Architect/Tech Lead revision count for this — it's a different failure class with its own cap.
- **Tech Lead fails, root cause is budget/timeline/scope, Product Manager bounce count already 1** → do not bounce again. Route to Delivery Manager.
- **Tech Lead fails, Architect/Tech Lead revision count at cap** → route to Delivery Manager regardless of root cause.
- **Tech Lead passes** → route to the Human Approval Gate.
- **Human Approval Gate rejects** → route back to Tech Lead (not Architect, not Product Manager — the Tech Lead decides from there whether the rejection reason is itself design-fixable or a planning issue, using the same root-cause logic above).
- **Human Approval Gate approves** → route to Developer.

**Build/validate/review loop (unchanged in shape from before, different acceptance-criteria source):**
- **Validator fails** → route back to Developer, increment the Developer/QA Engineer revision count. If that count is at cap: stop, route to Delivery Manager instead of another retry.
- **QA Engineer fails, Developer/QA Engineer revision count under cap** → route back to Developer with the QA Engineer's specific findings (against `spec.md`'s acceptance criteria) attached. Increment the count.
- **QA Engineer fails, count at cap** → route to Delivery Manager. Do not attempt another automatic retry regardless of how minor the last failure looked.
- **QA Engineer passes** → route to deploy.
- **Deploy completes** → route to Technical Writer for vault merge (`plan.md`, `design.md`, `spec.md`).

**PR feedback (resumes the existing build — never a new one):**
- **New review comments on the build's PR, `pr_review_rounds` under cap (3)** → increment `pr_review_rounds`, reset the Developer/QA Engineer revision count, and route all new comments to Tech Lead for scoping. Do not classify comments here — route on Tech Lead's per-comment label: `in-spec` → Developer → Validator → QA Engineer loop; `design-change` → Architect (Architect/Tech Lead count), then Tech Lead and the Human Approval Gate; `scope-change` → Product Manager (Product Manager bounce count), then the normal front of the pipeline and the gate; `question` / `out-of-scope` → no code change, surface to the human. Any design/scope change holds the in-spec fixes until the gate passes.
- **New review comments, `pr_review_rounds` at cap** → route to Delivery Manager.

**Cross-cutting:**
- **Reference-Graph Checker flags a stale `intake.md` ID citation** → route to Technical Writer. Do not attempt to auto-resolve this yourself even for what looks like a simple case — classifying "simple relabel" vs. "content change" is the Technical Writer's judgment call, not deterministic logic the Orchestrator should approximate.
- **Tech Lead's risk tier is high** (auth, payments, data migrations, public APIs) → require the human approval gate before proceeding past assessment, and again before deploy, regardless of how the automatic checks came out.
- **Budget/step cap reached, any stage** → stop and route to Delivery Manager, independent of any other counter.

## What it logs

Every transition, every cap check (pass or trip), and every routing decision — timestamped, per build — for audit and, on client work, billing traceability. Log which counter incremented on every bounce (Architect/Tech Lead, Product Manager bounce, or Developer/QA Engineer) so a build that thrashes across multiple loop types is visible as such, not just as "a lot of retries." This log is what the Delivery Manager formats for a human and what feeds `deviation-log.md` alongside the Technical Writer's own entries.

## What it explicitly does not do

- It does not evaluate the content of a plan, a design, a spec, an implementation, or a review — only their pass/fail/status signals, root-cause classifications, and the counts/caps around them.
- It does not decide whether a Tech Lead failure is design-fixable versus a planning problem — that classification comes from the Tech Lead itself, as part of its output; the Orchestrator just routes on it.
- It does not decide whether a stale reference is safe to auto-cascade — see Technical Writer.
- It is not a place to add "smart" retry logic (e.g. skipping a cap because a failure "looks minor"). If that kind of judgment is wanted, it belongs in an LLM-backed agent's prompt, reviewed as such — not quietly folded into the deterministic router where it can't be inspected the same way.
