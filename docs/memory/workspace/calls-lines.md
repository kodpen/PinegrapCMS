---
name: calls-lines
description: A Workspace call's channel lines (CL7, #11125): started, ended with length and who took part, missed or declined in a conversation of two; system lines, never to an agent
---

## Which lines does a call write?
- A new call (not a join of one already on): "@Ada started a voice call." / "… started a video call." (`ws_call_line_post_started()`, from `ws_call_start()`).
- When it ends: "Voice call ended · 12:05 · @Ada, Guest name, @Ben" (length from its start to its end, a silent call's end being when it was last heard, #11765, as 0:42, 12:05, 1:02:09; everyone ever in it once, the starter first, in the order of first join).
- In a conversation of two people (not a guest room) where nobody but the starter was ever in the call: "@Ben missed a voice call from @Ada." naming the other person; "@Ben declined a voice call from @Ada." when Ben declined it.
- A group call nobody joined writes the ended line with the starter alone: no ring, so nobody is named as missing it. A guest room's call is never missed.

## How are they written?
- `includes/workspace/call_lines.php`, through `ws_message_system()`: a person as the `<@user:id>` tag the channel draws as their name, a guest by `ws_guests.name` without markup ("A guest" when empty). Written in the language of the request that wrote them, like every system line.
- `ws_call_end_row($call, $declined_by)` writes the end line only when its own `UPDATE … AND state = 'live'` changed the row, so a leave, an end, a decline and the 2-minute clean-up racing each other write one line. `ws_call_decline()` passes who declined.
- No new column or table: which line is decided from `ws_call_members` (left rows too) and the channel's membership count (`ws_call_line_end_kind()`, pure).

## Activity, not a message to an agent?
- A system line moves the channel's `last_message_id` / `last_message_at` (its place in the lists, open conversations redraw), but it is not sent through `ws_message_send()`: no agent request, wake, webhook or mention comes of it, and system lines count in no unread number.

## Tests?
- `tests/workspace_call_lines_test.php`: length, parties, which line, the words, the one-writer guard, no `ws_message_send()`.
- Not run: two real browsers ending a call and reading the line.
