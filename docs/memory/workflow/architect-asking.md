---
name: architect-asking
description: How a session asks a decision question or the owner's approval of screenshots: search first, one architect issue each, a Türkçe özet block, then the session ends
---

## How is one asked?
- First search the open and closed `architect` issues for the plan file and the question: on 2026-10-27 six questions were opened twice within an hour. A worker opens only its own step's question; a plan's other open questions are the architect's.
- One issue per decision (numbered questions only when one change needs them all), labelled `architect`, by `mcp__github__issue_write` (create). A refused issue (permission check): the same text in the PR body under `## Decision question`; the architect opens it.
- Body: a `## Türkçe özet` block first (question, options in a line each, recommendation: the owner reads Turkish), then `## Context` (PR, plan step, files), `## Question`, `## Options` (what each changes, for running sites too, its cost), `## Recommendation`, `## Blocks` in English for the workers. No session or process trace (Fixed rules 2).
- Then `Decision: #<issue>` (or the section) in the PR body, the PR back to draft and its `ready` label off unless the PR does not wait for the answer, and the session ends (`session-lifecycle.md`): nobody waits for a decision.

## How is the owner's approval of a PR asked?
- A PR whose plan says "the owner approves each PR before merge" (screenshots, e.g. B10/B11): once green, the session opens an `architect` issue `Approval: #<pr> — <topic>` (`## Türkçe özet`: what changes on screen, the screenshot link, options Onaylıyorum / Değişiklik istiyorum), writes `Approval: #<issue>` in the PR body, keeps `ready` and ends. Without the issue the approval never reaches the owner (B10/B11, 2026-11-01).
- The architect asks it like a product question (`erdal`, a selectable list) and writes the answer on the PR. The merging session takes such a PR only after that answer, removes the screenshot link and the waiting note from the PR body, and closes the issue on landing.

Related: docs/memory/workflow/architect.md, docs/memory/workflow/session-lifecycle.md
