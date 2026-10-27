---
name: architect
description: Decision questions (2026-10-27): an issue labelled architect; the architect session analyses it, decides it with the owner (label erdal), records it, brings it to the PR, never decides alone
---

## Why an architect?
- Questions sat in PR bodies and session windows, the owner was asked technical questions without an analysis, and sessions settled logic clashes between PRs on their own. One session now gathers every question, analyses it and decides it together with the owner.

## What is a decision question?
- Product behaviour, a schema change (Fixed rules 5), an entry of the decisions list, an architecture question, a large migration or its order, a logic clash between two PRs (the merging session takes the PR out of the train and asks).
- Not one: what memory, the plan or the decisions list already answers, or an implementation choice inside the step (naming, structure within its files, tests). The session takes those and names the notable ones in its PR body. Nor a coordinator's choice of work (`coordinator.md`).

## How is one asked?
- One issue per decision (numbered questions only when one change needs them all), over REST: `gh api repos/kodpen/pinegrapcms/issues -f title='<topic>' -F body=@body.md -f 'labels[]=architect'`.
- Body, in English: `## Context` (what is built; the PR, plan step and files), `## Question`, `## Options` (what each changes, for running sites too, and its cost), `## Recommendation`, `## Blocks` (what waits). No session or process trace (Fixed rules 2).
- Then `Decision: #<issue>` in the PR body, the PR back to draft and its `ready` label off (`session-lifecycle.md`); wait subscribed to the PR.

## What does the architect do?
- One standing session on the model the owner chose. A session cron every 15 minutes on off-minutes and an hourly routine that re-creates it, as the merging session's (`merger-wake.md`). Each check lists the open `architect` issues; nothing new: no message.
- Analyses each new question from the code, memory and decisions list and writes the analysis on the issue: what each option changes, the risk for running sites, its recommendation. A question memory already answers is closed citing the file ("already decided: …"); any other gets the `erdal` label.
- Brings the `erdal` issues to the owner in its own window, in Turkish, as one list, blocking ones first, each with its recommendation, and decides them with the owner. It never decides alone, not even an obvious one.
- After the answer: a `Decision:` comment on the issue (the owner's words and the reason), `erdal` off; a comment on the PR quoting it, which wakes the worker; the decision recorded in memory by its own docs-only PR (product behaviour and schema in `decisions-made-*.md`, technical ones in the topic file or the plan), several decisions per PR; then the issue is closed.
- Writes no product code, opens no product PR, merges nothing.

## What do the other sessions do?
- The worker, woken by the comment, follows the decision, notes "decided in #<issue>" in its PR body and marks the PR ready again. It does not record the decision itself.
- The coordinator carries no questions. It names to the owner an `architect` issue left without an analysis for an hour (the architect seems stopped).
- The merging session keeps landing the rest of a train while a clash waits.

## Labels
- `architect`: every decision question, open until the decision is recorded. `erdal`: analysed, waiting for the owner, set only by the architect; the owner's list on GitHub is `label:erdal`.

## How is it started?
- A new session told: "You are the architect: read `CLAUDE.md` and `docs/memory/workflow/architect.md`, then keep the 15-minute check."
- Its first job: the open questions of the plan files (sections such as "What does the owner still answer?") become issues, one per decision, and the plan line names the issue.

Related: docs/memory/workflow/coordinator.md, docs/memory/workflow/session-lifecycle.md, docs/memory/workflow/decisions-made.md
