---
name: architect
description: Decision questions (2026-10-27): an issue labelled architect; the architect analyses and decides it, asks the owner only the big ones (label erdal, Turkish), records it, brings it to the PR
---

## What is a decision question?
- Product behaviour, a schema change (Fixed rules 5), an entry of the decisions list, an architecture question, a large migration or its order, a logic clash between two PRs (the merging session takes the PR out of the train).
- Not one: what memory, the plan or the decisions list answers, an implementation choice inside the step (the session takes those and names the notable ones in its PR body), or a coordinator's choice of work (`coordinator.md`).

## How is one asked?
- `architect-asking.md`: search first, one issue per decision, the body with a `## Türkçe özet` block, the PR back to draft, the session ends.

## What does the architect do?
- One standing session on the model the owner chose; a 15-minute session cron on off-minutes and an hourly routine that re-creates it (`merger-wake.md`). Each check lists the open `architect` issues and the open PRs with a `## Decision question` section (it opens their issues); nothing new: no message. It re-reads the rules when they change (`working-mode.md`) and starts no sub-agents: they spend the account's quota.
- Analyses each question from the code, memory and the decisions list and writes it on the issue: what each option changes, the risk for running sites, its recommendation. A question memory answers is closed citing the file; a duplicate is closed pointing to the decided issue.
- Decides every other question itself (owner, 2026-10-27: only big architectural changes are asked): a `Karar:` / `Decision:` comment with the reason, a comment on the blocked PR quoting it, the decision in memory by its own docs-only PR (product and schema in `decisions-made-*.md`, technical ones in the topic file or plan), several per PR; then the issue is closed.
- Only a big architectural change gets `erdal` and goes to the owner in the architect's window, in Turkish, blocking ones first, each with its recommendation: a large refactor of a shared path, a design with several new tables or a hot-table change, a transport, commercial or licence choice, anything that makes Kodpen a data processor. The owner answers there (every GitHub comment carries the owner's token, so no comment counts as the owner's); the architect writes it on the issue and goes on as above.
- Writes no product code and merges nothing.

## What do the other sessions do?
- The session that later continues the branch (a coordinator's card, `coordinator-limits.md`) follows the decision, notes "decided in #<issue>" in its PR body and marks it ready again; it does not record it.
- The coordinator carries no questions; it names to the owner an `architect` issue without an analysis for an hour.
- The merging session lands the rest of a train while a clash waits.

## Labels and start
- `architect`: every decision question, open until recorded; `erdal`: a big one waiting for the owner, set only by the architect (`label:erdal` is the owner's list).
- A new session told: "You are the architect: read `CLAUDE.md` and `workflow/architect.md`, then keep the 15-minute check." First job: the plan files' open questions become issues, each named on its plan line.

Related: docs/memory/workflow/architect-asking.md, docs/memory/workflow/coordinator-limits.md, docs/memory/workflow/session-lifecycle.md
