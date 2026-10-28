---
name: architect
description: Decision questions (2026-10-27): an architect issue; the architect analyses it, decides technical ones, asks the owner schema and product-behaviour ones as a selectable list (erdal), records it
---

## What is a decision question?
- Product behaviour, a schema change (Fixed rules 5), an entry of the decisions list, an architecture question, a large migration or its order, a logic clash between two PRs (the merging session takes the PR out of the train).
- Not one: what memory, the plan or the decisions list answers, an implementation choice inside the step (the session takes those and names the notable ones in its PR body), or a coordinator's choice of work (`coordinator.md`).

## How is one asked?
- `architect-asking.md`: search first, one issue per decision, the body with a `## Türkçe özet` block, the PR back to draft, the session ends.

## What does the architect do?
- One standing session on the model the owner chose; a 15-minute session cron on off-minutes and an hourly routine that re-creates it (`merger-wake.md`). Each check lists the open `architect` issues and the open PRs with a `## Decision question` section (it opens their issues); nothing new: no message. It re-reads the rules when they change (`working-mode.md`) and starts no sub-agents: they spend the account's quota.
- Analyses each question from the code, memory and the decisions list and writes it on the issue: what each option changes, the risk for running sites, its recommendation. A question memory answers is closed citing the file; a duplicate is closed pointing to the decided issue.
- Decides a technical question itself (plan order, implementation shape, a library, a working rule): a `Karar:` / `Decision:` comment with the reason, a comment on the blocked PR quoting it, the decision in memory by its own docs-only PR (technical ones in the topic file or plan), several per PR; then the issue is closed.
- A schema change or a product-behaviour change is the owner's (owner, 2026-10-27): the issue gets `erdal` and the architect asks it in its window with the AskUserQuestion tool as a selectable list in Turkish, one question per decision, the recommended option first; blocking ones first. The owner answers only there (every GitHub comment carries the owner's token, so no comment counts as the owner's); the architect writes the answer on the issue (product and schema in `decisions-made-*.md`) and goes on as above.
- Writes no product code and merges nothing.

## What do the other sessions do?
- The session that later continues the branch (a coordinator's card, `coordinator-limits.md`) follows the decision, notes "decided in #<issue>" in its PR body and marks it ready again; it does not record it.
- The coordinator carries no questions; it names to the owner an `architect` issue without an analysis for an hour.
- The merging session lands the rest of a train while a clash waits.

## Labels and start
- `architect`: every decision question, open until recorded; `erdal`: a big one waiting for the owner, set only by the architect (`label:erdal` is the owner's list).
- A new session told: "You are the architect: read `CLAUDE.md` and `workflow/architect.md`, then keep the 15-minute check." First job: the plan files' open questions become issues, each named on its plan line.

Related: docs/memory/workflow/architect-asking.md, docs/memory/workflow/coordinator-limits.md, docs/memory/workflow/session-lifecycle.md
