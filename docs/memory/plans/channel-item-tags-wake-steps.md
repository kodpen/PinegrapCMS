---
name: channel-item-tags-wake-steps
description: Steps TW1-TW5 of the channel-item-tags-wake plan: file caption, edit, note and decision; other messages; polls, tasks; shared notes, table cells (agent channels, managers); close-out
---

## Which steps are there?
- Each about 1,000 product lines at most, in this order; a step starts when the one before it has landed. Function and file names are the worker's call. Tests in `tests/` for the helper's pure parts (tags added, which path asks).

- TW1 The helper; a message with a file; an edit; note and decision. Done: #9359 (`workspace/channel-item-tags.md`).
  - The helper above, with its pure tag-diff function.
  - A message with any file, not only a recording: the panel upload (`ws_message_send_file_bytes()` files.php, with `ws_voice_posted()` still holding a queued recording until transcribed), the file-manager attach (`ws_library_send()` library.php), the API's files.create (`ws_api_files_create()` api_resources.php).
  - An edit (`ws_message_edit()` messages.php, panel and API): only the tags it adds, beside `ws_message_notify_mentions()`'s diff of people.
  - /note and /decision (`ws_command_run()` commands.php), the API kinds note and decision (api_resources.php, its comment "kept, not asked" changed), meeting decisions (`ws_meeting_note_transfer()` meetings.php).
  - Memory: `workspace-voice-asks.md` ("any other file still asks nobody" corrected), `agent-words-wake.md`, a new file for the helper.
  - After: a picture with `@agent` beside it, an edit adding `@agent`, a /decision naming `@agent` each make one request; an edit that keeps the tag makes none.
- TW2 The other messages written for a person or an application. Census: ~10 sites, ~300 lines.
  - Forward with a comment (`ws_forward_to_channel()` forward.php), approval request (`ws_approval_create()` approvals.php), status update and check-in question (`ws_status_post()`, `ws_checkin_start()` status.php), template welcome and pinned text (`ws_template_apply()` templates.php), the scheduled action and flow "Post a message" box (`ws_scheduled_do()` scheduled.php).
  - After: each of them naming `@agent` makes one request; an application's approval or check-in wakes only by mention or reply.
- TW3 (agent channels, owner or channel manager only; #9341) Polls, check-ins, forwards, tasks. Census: ~400 lines.
  - Poll question and options (`ws_poll_create()`, `ws_poll_edit()` interact.php), check-in answers (`ws_checkin_answer()` status.php), forwarded text (`ws_message_forwards.body`), task title and description (`ws_tasks`), task notes (`ws_task_note_add()` task_work.php: registry agents too, beside Claude, AI and MCP through delegation.php).
  - The request hangs on the item's message (poll, check-in, forward, the task's card); an item with none is a question for the architect before the step starts.
  - The writer's check (agent switch on, owner or channel manager) is one function shared by TW3 and TW4.
  - After: a poll option or task note naming `@agent`, written by the channel's manager in an agent channel, makes one request; a member's, or one elsewhere, makes none; an edit asks only added tags.
- TW4 (agent channels, owner or channel manager only; #9341) Shared notes, workflow table cells; never a canvas. Census: ~500 lines.
  - Shared note content (`ws_note_share_channel()` notes.php and its edits), table cells (`ws_wtable_row_create()`, `ws_wtable_row_update()` wtable_rows.php). Canvas text layers wake nobody (owner, #9341).
  - These have no message of their own: which message a request hangs on and what the brief tells the agent of where the tag is, is asked of the architect when TW3 lands.
  - After: a cell naming `@agent` written by the channel's manager in an agent channel makes one request; a save that keeps it, or a member's cell, makes none.
- TW5 (close-out, docs only) The rule written into `workspace/agent-words-wake.md` and `workspace-channel-overview.md`; the decisions line checked; both plan files deleted.
