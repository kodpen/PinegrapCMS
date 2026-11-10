---
name: architect-no-hand-over
description: The architect session does not hand over to a fresh session at a token count (2026-11-10): auto-compact summarises its context in place; what it reads again after a compaction
---

## Does the architect hand over to a fresh session?
- No, since 2026-11-10, as the coordinators (`coordinator.md`): auto-compact summarises the session's context in place, so the session, its tick chain, its hourly routine and its channel waiter stay. Until then the hourly routine wrote a hand-over note past about 350k tokens.
- Why: a hand-over costs a new session and the owner's time to start it, and the note loses whatever it does not name.
- A hand-over note is written only when the owner asks for a fresh architect.

## What does it read after a compaction?
- The state from GitHub and memory, not from the summary: `CLAUDE.md` and `architect.md`, the open `architect` issues, the open PRs with a `## Decision question` section or a `Decision: #` / `Approval: #` line, its own open PR, and `GET /workspace/agent-requests`.
- Its schedules by name (`list_triggers`): one pending "Mimar: 45-minute tick" and the hourly "Mimar: hourly re-arm". The channel waiter is started again when none runs (`agent-channel-wake.md`).

Related: docs/memory/workflow/architect.md, docs/memory/workflow/coordinator.md
