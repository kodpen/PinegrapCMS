---
name: agent-request-closed-by-site
description: When the site closes an app agent's request itself: unclaimed for a day (cancelled), claimed and silent for a day (failed)
---

## When is an app agent's request closed without the agent?
- Both by the sweep `ws_agent_queue_expire()` (`includes/workspace/agent_queue.php`), run from `ws_agent_knocks_retry()` with the queue's other work. The limit is `WS_AI_QUEUE_TTL` (a day) for both.
- Unclaimed (`queued`/`sent`) for a day: cancelled; the line under the message says "The agent did not take it within a day."; the message is stamped.

## What happens to a claimed request whose agent went silent?
- A `running` request is closed as failed when a day has passed since its last sign of life: the later of `claimed_at` and the last progress note's `progress_at` in `ai_state`. Each progress note starts the day again.
- SQL takes running `agent:%` rows with `claimed_at` older than a day (`ws_agent_queue_stalled_where()`); the pure `ws_agent_request_stalled()` decides each row by its note.
- The close: status failed, error "The agent did not finish it within a day." (the line under the asking message shows it), the progress note cleared, the eye taken off, the message stamped so open screens redraw, a flow's agent box told. A wake or a task run with no message closes silently.
- The update holds only over the state it read (`status = 'running'` and the same `ai_state`), so a note, answer or Cancel in between wins. A late answer or fail gets 409 `closed`.
- A person's Cancel on the Agents card stays the way to close one sooner.

## Why a day and not 15 minutes?
- Architect (2026-11-26): Claude and Pinegrap AI fail theirs after 15 minutes (`WS_CLAUDE_STALE`, `WS_AI_STALE`), but an app agent may work for hours; the instructions ask for a progress note in long work, so only an agent that is gone is closed. Before, a claimed request stayed running for ever.
- Tests: `tests/workspace_agent_queue_test.php`.
