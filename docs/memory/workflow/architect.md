---
name: architect
description: Decision questions (2026-10-27): an issue labelled architect; the architect analyses and decides it, asks the owner only the big ones (label erdal, Turkish), records it, brings it to the PR
---

## What is a decision question?
- Product behaviour, a schema change (Fixed rules 5), an entry of the decisions list, an architecture question, a large migration or its order, a logic clash between two PRs (the merging session takes the PR out of the train and asks).
- Not one: what memory, the plan or the decisions list answers, or an implementation choice inside the step (naming, structure, tests): the session takes those and names the notable ones in its PR body. Nor a coordinator's choice of work (`coordinator.md`).

## How is one asked?
- One issue per decision (numbered questions only when one change needs them all), labelled `architect`: `mcp__github__issue_write` (create) in a cloud session, else `gh api repos/kodpen/pinegrapcms/issues -f title='…' -F body=@body.md -f 'labels[]=architect'`.
- Body: a `## Türkçe özet` block first (question, options in a line each, recommendation: the owner reads Turkish), then `## Context` (PR, plan step, files), `## Question`, `## Options` (what each changes, for running sites too, its cost), `## Recommendation`, `## Blocks` in English for the workers. No session or process trace (Fixed rules 2).
- If the permission check refuses the issue, never work around it: the same text goes in the PR body under `## Decision question`; the architect opens the issue from it.
- Then `Decision: #<issue>` (or the section) in the PR body, the PR back to draft and its `ready` label off unless the PR does not wait for the answer (`session-lifecycle.md`); wait subscribed.

## What does the architect do?
- One standing session on the model the owner chose; a 15-minute session cron on off-minutes and an hourly routine that re-creates it (`merger-wake.md`). Each check lists the open `architect` issues and the open PRs with a `## Decision question` section (it opens their issues); nothing new: no message.
- Analyses each question from the code, memory and the decisions list and writes it on the issue: what each option changes, the risk for running sites, its recommendation. One memory answers is closed citing the file.
- Decides every other question itself (owner, 2026-10-27: only big architectural changes are asked): a `Karar:` / `Decision:` comment with the reason, a comment on the blocked PR quoting it (it wakes the worker), the decision in memory by its own docs-only PR (product and schema in `decisions-made-*.md`, technical ones in the topic file or plan), several per PR; then the issue is closed.
- Only a big architectural change gets `erdal` and goes to the owner in the architect's window, in Turkish, blocking ones first, each with its recommendation: a large refactor of a shared path, a design with several new tables or a hot-table change, a transport, commercial or licence choice, anything that makes Kodpen a data processor. The owner answers there; the architect writes it on the issue and goes on as above.
- Writes no product code and merges nothing.

## What do the other sessions do?
- The worker, woken by the comment, follows the decision, notes "decided in #<issue>" in its PR body and marks it ready again; it does not record it.
- The coordinator carries no questions; it names to the owner an `architect` issue without an analysis for an hour.
- The merging session lands the rest of a train while a clash waits.

## Labels
- `architect`: every decision question, open until recorded. `erdal`: a big one waiting for the owner, set only by the architect; the owner's list is `label:erdal`.

## How is it started?
- A new session told: "You are the architect: read `CLAUDE.md` and `docs/memory/workflow/architect.md`, then keep the 15-minute check." First job: the plan files' open questions become issues; the plan line names each.

Related: docs/memory/workflow/coordinator.md, docs/memory/workflow/session-lifecycle.md, docs/memory/workflow/decisions-made.md
