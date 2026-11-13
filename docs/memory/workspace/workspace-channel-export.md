---
name: workspace-channel-export
description: A channel's conversation as a Markdown file or a print page (workspace_channel_export.php): range, what it holds (polls, approvals, tasks, files), the 5,000 limit, audit line, the drawer
---

## What leaves, and from where?
- `workspace_channel_export.php?channel=N[&from=Y-m-d][&to=Y-m-d][&print=1]`: Markdown (`<channel>-<date>.md`) or a page of its own that calls `window.print()` (the browser saves it as PDF). GET with the session; `ws_screen_gate()`, then `ws_can_read_channel()` (else 404). A guest has no panel and never reaches it.
- The button: Export in the channel menu (`channelMenu()`, `exportForm()` in workspace.js, `CFG.urls.channel_export`, strings `chx_*` in screen.php); hidden while an earlier version of the conversation is shown. The drawer asks for two dates (empty = all) and the form.

## What does it hold?
- Head: channel name, range (`ws_channel_export_range_label()`), who exported and when. Then the decisions (`ws_channel_decisions()`, oldest first, one line each), the messages oldest first grouped by day, the file list (`ws_channel_files()`). Decisions and files are cut to the range too.
- What the reader sees: payloads from `ws_message_payloads()` (messages they hid stay out), deleted ones out (`ws_channel_export_keep()`). Markdown text: tags through `ws_note_export_tokens()` with absolute links; the print page uses the payload `html` (`ws_render_body()`).
- A poll is one line (`Poll: … — answer count · …`, `(closed)`), an approval its outcome sentence (`ws_channel_export_approval_line()`), a file `[name](url)`.
- A task message (kind `task`, empty body) is `Task: G-44 · title · status · Due: 15.11.2026 · names` (`ws_channel_export_task_line()`); the due date is a fixed day (`ws_channel_export_task_due()`), never "Today". `ws_channel_export_tasks()` reads the tasks once for the whole export, discussions included; a task the reader may not see (`ws_can_see_task()`) is `Task: G-44` only, as its card. The print page draws the payload's `task_html` card. Before, the Markdown left only the head line (#9043).
- A discussion (`ws_threads` channel) goes quoted under its message only when the reader may read it; its messages count to the limit. Only the current conversation (after the last clear, `ws_messages_page()` floor).

## What are the limits?
- At most `WS_CHANNEL_EXPORT_MAX` (5,000) messages; more refuses with "Choose a shorter range." Rows are read 200 at a time from the newest (`ws_channel_export_rows()`).
- Each export writes `pg_audit('export', 'ws_channel', id, [], {format, from, to, messages}, name)`: a channel may hold customers' data.

## Where are the tests?
- `tests/workspace_channel_export_test.php`: range, keep, tags, discussion, poll, approval and task lines, empty range, file name. The pure builders are `ws_channel_export_markdown()` and its helpers; `ws_channel_export_collect()` reads the database.
- Later (not built): a zip with the files themselves; an export route in the API.
