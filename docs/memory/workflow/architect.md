---
name: architect
description: Decision questions (2026-10-27): an issue labelled architect; the architect session analyses it, decides it with the owner (label erdal), records it, brings it to the PR, never decides alone
---

## Why an architect?
- Questions sat in PR bodies and windows, the owner got technical ones without an analysis, and sessions settled clashes between PRs alone. One session now gathers them and decides them with the owner.

## What is a decision question?
- Product behaviour, a schema change (Fixed rules 5), an entry of the decisions list, an architecture question, a large migration or its order, a logic clash between two PRs (the merging session takes the PR out of the train and asks).
- Not one: what memory, the plan or the decisions list answers, or an implementation choice inside the step (naming, structure within its files, tests); the session takes those and names the notable ones in its PR body. Nor a coordinator's choice of work (`coordinator.md`).

## How is one asked?
- One issue per decision (numbered questions only when one change needs them all), labelled `architect`: `mcp__github__issue_write` (create) in a cloud session, else `gh api repos/kodpen/pinegrapcms/issues -f title='…' -F body=@body.md -f 'labels[]=architect'`.
- Body, in English: `## Context` (the PR, plan step, files), `## Question`, `## Options` (what each changes, for running sites too, its cost), `## Recommendation`, `## Blocks`. No session or process trace (Fixed rules 2).
- If the session's permission check refuses the issue, never work around it: put the same text in the PR body under `## Decision question`; the architect opens the issue from it.
- Then `Decision: #<issue>` (or the section) in the PR body, the PR back to draft and its `ready` label off unless the PR does not wait for the answer (`session-lifecycle.md`); wait subscribed.

## What does the architect do?
- One standing session on the model the owner chose; a session cron every 15 minutes on off-minutes and an hourly routine that re-creates it, as the merging session's (`merger-wake.md`). Each check lists the open `architect` issues and the open PRs with a `## Decision question` section (it opens their issues); nothing new: no message.
- Analyses each question from the code, memory and decisions list and writes it on the issue: what each option changes, the risk for running sites, its recommendation. A question memory answers is closed citing the file; any other gets `erdal`.
- Brings the `erdal` issues to the owner in its own window, in Turkish, as one list, blocking ones first, each with its recommendation, and decides them with the owner. Never alone, not even an obvious one.
- Then: a `Decision:` comment on the issue (the owner's words, the reason), `erdal` off; a comment on the PR quoting it, which wakes the worker; the decision in memory by its own docs-only PR (product and schema in `decisions-made-*.md`, technical ones in the topic file or plan), several per PR; the issue closed.
- Writes no product code and merges nothing.

## What do the other sessions do?
- The worker, woken by the comment, follows the decision, notes "decided in #<issue>" in its PR body and marks it ready again; it does not record the decision.
- The coordinator carries no questions; it names to the owner an `architect` issue without an analysis for an hour.
- The merging session lands the rest of a train while a clash waits.

## Labels
- `architect`: every decision question, open until recorded. `erdal`: analysed, waiting for the owner, set only by the architect; the owner's list is `label:erdal`.

## How is it started?
- A new session told: "You are the architect: read `CLAUDE.md` and `docs/memory/workflow/architect.md`, then keep the 15-minute check." First job: the plan files' open questions become issues, and the plan line names each.

Related: docs/memory/workflow/coordinator.md, docs/memory/workflow/session-lifecycle.md, docs/memory/workflow/decisions-made.md
