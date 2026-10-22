---
name: workspace-api-sync-inbox
description: Keeping a Workspace app current through the API: GET /workspace/sync cursor marks, inbox deletions and reach changes for changed_since, hidden messages and touched_at, assistants carried on
---

## How is a new part added to GET /workspace/sync?
- A part sent "only when it changed" writes its mark into the cursor as an OPTIONAL key (`ws_api_sync_cursor_write()` in `api_sync.php`, like `p`): not written while empty, read as empty when missing. Otherwise the cursors apps already hold get 400, or everything comes once more.
- A count that rests on another permission (the bell needs `account:read`) is `null` without it, not a refusal.

## How must code remove or change inbox lines?
- Never `DELETE FROM ws_inbox` directly: `ws_inbox_delete($where)` (`notify.php`) keeps a trace in `ws_inbox_deleted` (30 days), and only through it do `GET /workspace/inbox?changed_since=` and the sync's `inbox_changed_at` see the deletion.
- Code that changes a line in place moves its `created_at` (sent again) or `read_at`: changed lines are found by those two stamps alone.
- Code that takes people out of a channel's reach (membership removed, channel closed or made private) calls `ws_inbox_reach_lost($channel, $user_ids)`: their lines about it are marked read and come with `deleted: true`. Code that brings them back (added to a private channel, channel opened, discussion rejoined) calls `ws_inbox_reach_regained($channel, $user_ids)` for those who could not read it before: their read lines' `read_at` moves and they come back as live lines.
- A listing's own envelope field (`reload`) goes through `api_ok_list()`'s `$extra` and is declared beside `'list'` in the route's `returns`.

## How is a per-person message state kept?
- "Delete for me" (`ws_message_hides`) does NOT move the message's `touched_at`: every screen and device in the channel would redraw it for nothing. The reader's own moment (`hidden_at`) enters `ws_messages_touched()` through `$user_id` (`ws_channel_news()` option `user_id`); the message then comes back to that reader alone, `hidden: true` in the API, as deleted in the panel. New code reading a channel's changed messages passes the reader.

## How does an assistant's long job run from the API?
- Pinegrap AI and an MCP agent take a model call or an agent call. The endpoint carries them on with `ws_api_assistants_carry_on()` at the place `ws_api_carry_on_where()` gives: after the answer on PHP-FPM (`fastcgi_finish_request()`); on IIS only inside `GET /workspace/sync` polled by an open app, one step per call; never in the message write (the next sync, an open screen or the hourly job carries on). A new endpoint that starts long work does the same and never holds its answer for it.
