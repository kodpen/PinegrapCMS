---
name: architect-no-hand-over
description: The architect session does not hand over to a fresh session at a token count (2026-11-10): auto-compact at 300k (2026-11-23) summarises its context in place; what it reads again after a compaction
---

## Does the architect hand over to a fresh session?
- No, since 2026-11-10, as the coordinators (`coordinator.md`): auto-compact summarises the session's context in place, so the session and its channel waiter stay (`architect-wake.md`). Until then the hourly routine wrote a hand-over note past about 350k tokens.
- Why: a hand-over costs a new session and the owner's time to start it, and the note loses whatever it does not name.
- Its auto-compact window is 300k tokens since 2026-11-23 (#11186), 400k before: the owner types `/autocompact 300k` in its session, and in each new architect. Every call reads the whole context again (322,600 of 400,000 tokens on 2026-11-23, messages 253,900), and a session stands at about 95,000 right after a compaction, so a smaller window makes every call cheaper (`quota-use-measured.md`). The coordinators run at 200k (`coordinator.md`).
- A hand-over note is written only when the owner asks for a fresh architect.

## What does it read after a compaction?
- The state from GitHub and memory, not from the summary: `CLAUDE.md` and `architect.md`, its request queue in the agent channel, the open PRs with a `## Decision question` section or a `Decision:` / `Approval:` line, its own open PR, and `GET /workspace/agent-requests`.
- Its schedules by name (`list_triggers`): the hourly "Mimar: hourly check" stays disabled (`architect-wake.md`). The channel waiter is started again when none runs (`agent-channel-wake.md`).

Related: docs/memory/workflow/architect.md, docs/memory/workflow/coordinator.md
