---
name: agent-gone-request-and-schedule
description: Decided, not built yet: a claimed app agent request silent for a day is closed as failed; an agent in no channel keeps its schedule but gets no wake
---

## What was asked?
- The bug hunt of the agents' request queue (#11742) left two points to decide: a claimed (`running`) request of an app agent whose session stopped stays running for ever, with the eye and "is working on it" on the message; an agent removed from every channel keeps its schedule and each wake is made with channel 0.
- Architect (2026-11-26, agent channel request 3017) decided both. Neither adds a setting or a schema change. They are built as one small task once #11742 has landed (same files); the building PR corrects this file and writes the behaviour into `agent-doors.md` and `agent-wakes.md`.

## When is a claimed request closed?
- By silence, not by length: a `running` request of an app agent (`agent:%`) is closed as failed when a day (`WS_AI_QUEUE_TTL`, the value the unclaimed expiry uses) has passed since its last sign of life, the later of `claimed_at` and the last progress note's `progress_at` in `ai_state`.
- Not the 15 minutes of Claude's and Pinegrap AI's requests: an app agent works for hours. A progress note starts the day again, and the agents' instructions ask for one in long work, so only an agent that is gone is closed.
- Where: the sweep that cancels the unclaimed ones (`ws_agent_queue_expire()`). SQL takes the rows by `claimed_at`; a pure function decides each row by `progress_at`.
- The close: status failed, the line under the asking message as a fail writes it, reason "The agent did not finish it within a day." through `lang()`, the eye taken off, the message stamped. A wake or a task run with no message closes silently.
- A late answer gets 409 `closed`. A person's Cancel on the Agents card stays the way to close one sooner.

## What happens to the schedule of an agent in no channel?
- It is kept: the schedule is the agent's own setting (the Agents card, its own `agent-wake` route), and moving an agent from one channel to another (remove, then add) would lose it without a word.
- No wake is made while the agent has no allowed channel: `ws_agent_wakes_run()` makes no request and the schedule moves on from the due time, as it does when an earlier wake still waits. Why: the brief of such a wake only says "You are in no channel now: close this request with no_answer", and behind a routine door every knock starts a cloud session for nothing.
- Added to a channel again, the next due time wakes it. The card and `/wake @agent` say the schedule as before; the brief's line stays for an agent removed between the wake and the brief.

Related: docs/memory/workspace/agent-doors.md, docs/memory/workspace/agent-wakes.md
