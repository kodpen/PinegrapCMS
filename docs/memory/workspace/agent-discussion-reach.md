---
name: agent-discussion-reach
description: A channel's registry agent asked in the channel's discussions by name (A4b3 #8600): ws_agent_reach_rows(), the merged row, its readers, what stays the discussion's own
---

## What does a discussion take from its channel?
- #8600, on the architect's decision (`workflow/decisions-made-workspace-12.md`): a discussion with no `ws_channel_agents` row of its own for an agent takes the allowed row of the channel it is about, read live, never copied, for an ask by name only (`@handle` or `<@app:N>`).
- The symptom it fixed: in a discussion of a channel that allows `@agent`, writing `@agent` woke nothing, the @ picker did not offer the agent, and the writer got the "cannot read" line when the agent's owner did not read the discussion.

## How is it built?
- `ws_agent_reach_merge($own, $own_ids, $parent, $parent_rows)` (pure, `includes/workspace/agent_queue.php`): the discussion's own allowed rows as they are; for every agent with no row of its own (`$own_ids` counts rows not allowed too) the channel's row with `wake_rule` mention, no prefix, `tier` 1, `loop_guard` 1 and `reach_from` the channel's id. An archived (`archived_at` > 0) or missing channel gives none.
- `ws_agent_reach_rows($channel)`: a channel's rows unchanged (`ws_agent_channel_rows()`); in a discussion the merge with `ws_thread_parent_channel()`. `ws_agent_reach_row($channel, $agent_id)`: the raw row that decides one agent, its own or, in a discussion with none, the unarchived channel's.
- Loop guard on: the session's choice, so an agent reached from the channel stays braked by the guard as a discussion with no own row was before.

## Who reads it?
- The wake (`ws_agent_after_send()`): so the channel's `all`, `prefix` and `replies` never pass; the brakes and the own-message rule hold.
- The answer's place (`ws_api_agent_request_place()`: 403 once the agent is out of the channel or the channel is archived or gone; writer from `ws_assistant_writer()`), the retry (`ws_agent_activity_retry()`), the @ picker (`ws_channel_app_picker()`, same order, never twice) and the unread line's `$allowed` (`ws_message_tell_apps()`; the `workspace.app.mentioned` event still goes only where the owner reads).
- The model assistant's door: `ws_model_channel_allowed()` takes `ws_agent_reach_row()`; a guest reading along (the discussion or its channel) and `ai_access` 2 still close it.

## What stays the discussion's own?
- The tier (`ws_channel_agent_tier_of()`: tier 1 with no own row, it never reads the channel), `ws_agent_brief()` (no own row: the loop-guard text), `/wake` and schedules, the flow box, the Memory tab, the rows' writers.
- A discussion following an agent takes the same reaching row (#8611, `agent-discussion-follow.md`).
- Tests: `tests/workspace_agent_discussion_reach_test.php`.
