---
name: poll-agent-question-dry-run
description: A dry run of POST /workspace/channels/{id}/polls reports the settings an agent question keeps (anonymous false, closes_at null) and agent_question (#9812)
---

## What does a poll's dry run report?
- `ws_api_polls_create()` builds the rehearsal with `ws_api_poll_create_rehearsal()`: channel_id, options, multiple, anonymous, closes_at, agent_question, after `ws_agent_question_settings()`.
- The flag is the one `ws_poll_create()` stores: `ws_agent_question_ready() ? ws_agent_question_flag($app_id) : 0`. An application's rehearsal answers `anonymous: false`, `closes_at: null`, `agent_question: true`; a person's keeps what was sent.

## Why, and what was wrong before?
- Before #9812 the rehearsal echoed the request's anonymous and closes_at, while the real call made a non-anonymous agent question that never closes by time: the dry run promised settings the write did not keep.
- The change route already reported the kept values through `ws_poll_edit_check()`.

## Which test keeps it?
- `tests/workspace_api_polls_test.php`: the pure helper for both flags, and source order (the flag expression and the helper before `api_dry_run_stop()`).
