---
name: workspace-polls-api
description: A poll over the Workspace API: closed_at, can_vote false past closes_at and in an earlier version (ws_poll_vote_state()), and the field a vote, change or close refusal names
---

## What does the poll on a WorkspaceMessage carry about its closing?
- `closes_at` (when its time runs out) and `closed_at` (when it was closed, ISO-8601 UTC, null while open; since #2083). `closed` follows `closed_at` only.
- Nothing scheduled closes a poll: the first vote after `closes_at` (API or panel) or a read of its channel (`ws_polls_autoclose()`) does. A poll past `closes_at` can therefore still show `closed: false`.

## When is can_vote true?
- `ws_polls_map()` asks `ws_poll_vote_state($poll, ws_poll_in_past($poll))` (interact.php), the same state `ws_poll_vote_check()` starts with, plus `ws_can_post_channel()`. So `can_vote` is false past `closes_at` before anything closes the poll and for a poll in an earlier version of the conversation (eras.php); the panel card and the API agree with the vote.
- `can_edit` and `can_close` are unchanged: still `!closed` and who the person is.

## Which field does a refusal name?
- All poll refusals go through `ws_api_poll_refused($error, $field)`.
- `message_id`: the poll's message is in an earlier version (`ws_api_poll_or_404()`, so vote, change and close alike).
- `closes_at`: the poll is closed or its time ran out ("The poll is closed.", 422 `validation_failed`). The vote gets it from `ws_poll_vote_state()`; the change's `ws_poll_edit_check()` gives no field, so `ws_api_poll_refused()` fills `closes_at` for that text.
- `option_ids`: an unknown option or several in a single-choice poll. "You cannot post in that channel." stays 403 `forbidden`.
- A dry run calls the same checks before `api_dry_run_stop()`, so it answers the same status, code and field; only the closing of an expired poll is skipped.

**Why:** the API said `can_vote: true` on a poll past its time while the vote failed, and both refusals were a 422 without a field, told apart only by translated text (#2083).
