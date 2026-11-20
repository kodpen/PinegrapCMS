---
name: channel-commands-answer-census
description: K28 census (CC12 of channel-commands): an application command's answer today, what one person sees and where it is kept, a phone and a second window; the proposed shape
---

## How does an application command answer today?
- `ws_app_command_send()` (`includes/workspace/app_commands.php`) sends `workspace.app.command` (`pg_announce()`, payload `ws_app_command_payload()`) and returns `ephemeral` "Sent to the application …"; the line is never posted. An agent's command posts the line instead (`agent-commands.md`).
- The application can only answer for everyone: `POST /workspace/channels/{id}/messages`. The payload carries `user_id` and `channel_id` but no handle of the command, so no answer can be tied to it.

## What is shown to one person today?
- A command's `ephemeral` answer (/recap, /remind, /notify, unknown command, a missing argument): HTML in the `ws_send` reply. `send()` in `assets/js/workspace.js` draws it as a `.ws-ephemeral` box at the end of the pane with a close ×.
- The unread-mention line: `notice` of `ws_send` / `ws_edit`, drawn by `mentionNotice()` (`workspace-app-mention-unread-line.md`).
- Neither is stored: the box lives only in the window that sent; a channel change or reload (`clear(self.pane)`) loses it.

## What is already kept for one person?
- `ws_inbox` (`user_id`, `kind`, channel, message, actor; no body): kept until read or `ws_inbox_delete()`; it rings the bell and push.
- `ws_message_hides` ("Delete for me"): reaches that reader alone through `ws_messages_touched()` with `user_id`, inside `ws_sync`.
- `ws_call_signals` (`to_member_id`, `body`): one member to another, read by `ws_sync`'s `call` part, deleted after `WS_CALL_SIGNAL_LIFE` (120 s).
- `ws_scheduled_actions`: the writer's own, counted in `ws_sync` as `scheduled_mine`.
- None of them holds an application's text for one person.

## What do a phone and a second window see?
- The phone is the same panel screen and the same `ws_sync` (every 4 s on an open channel, `includes/workspace/actions.php`); there is no separate app.
- A command's answer reaches only the window whose `ws_send` it answered. Another window, or the phone, sees nothing: no sync part, no inbox line, no push.
- An application's later answer is asynchronous (after the webhook), so it can only reach the writer through a stored row that `ws_sync` reads.

## What shape is proposed?
- A new table, asked of the owner: `ws_app_answers` (`id`, `user_id`, `channel_id`, `app_id`, `command`, `body` TEXT, `created_at`), kept 24 hours and swept in maintenance like `ws_call_signals`.
- The `workspace.app.command` payload gets `answer_token`: signed with the site's secret over user, channel, app and time, valid 15 minutes, nothing stored. An application can then answer only the writer of a command it received, never anyone at any time.
- One route, `POST /workspace/commands/answer` (`token`, `text`; scope `workspace:commands`, `dry_run`, `'mcp' => false`). The text is cleaned as a message body. One answer per token, or at most three.
- `ws_sync` gains `answers` for the open channel after an `answer_after` id. Every window and the phone draw it as a `.ws-ephemeral` box under the application's name, with a close × that hides it in that window only. No inbox line, no push, no bell.
- Gate: the writer must still read the channel; a removed member is not sent the row.
- Without the table: nothing to build. The application posts a normal message, as today.
