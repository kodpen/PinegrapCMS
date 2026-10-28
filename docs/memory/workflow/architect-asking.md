---
name: architect-asking
description: How a session asks a decision question (2026-10-27): search the architect issues first, one issue per decision, the body with a Türkçe özet block, the PR back to draft, then the session ends
---

## How is one asked?
- First search the open and closed `architect` issues for the plan file and the question: on 2026-10-27 six questions were opened twice within an hour. A worker opens only its own step's question; a plan's other open questions are the architect's.
- One issue per decision (numbered questions only when one change needs them all), labelled `architect`, by `mcp__github__issue_write` (create). A refused issue (permission check): the same text in the PR body under `## Decision question`; the architect opens it.
- Body: a `## Türkçe özet` block first (question, options in a line each, recommendation: the owner reads Turkish), then `## Context` (PR, plan step, files), `## Question`, `## Options` (what each changes, for running sites too, its cost), `## Recommendation`, `## Blocks` in English for the workers. No session or process trace (Fixed rules 2).
- Then `Decision: #<issue>` (or the section) in the PR body, the PR back to draft and its `ready` label off unless the PR does not wait for the answer, and the session ends (`session-lifecycle.md`): nobody waits for a decision.

Related: docs/memory/workflow/architect.md, docs/memory/workflow/session-lifecycle.md
