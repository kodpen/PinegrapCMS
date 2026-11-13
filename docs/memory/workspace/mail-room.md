---
name: mail-room
description: A mailbox kept in no channel (WM13, #8985): its room of kind mailbox, the box form's Kept in, who reads and answers, where the room never shows, the inventory test
---

## What is it?
- Owner (2026-11-11, #8765): a box may be tied to no channel. Its form's "Kept in" offers "A channel" or "The mailbox itself"; the second keeps its mail in a room: a `ws_channels` row of kind `mailbox` (value added by 11.0.188, `mail-html.md`), named after the box (80 characters), no owner, no topic.
- `ws_mail_boxes.channel_id` is the room's id, so ingest, the list, the pane, unread and files work by the conversation's channel as for any box. Module `includes/workspace/mail_room.php`.

## What does the box keep in `settings`?
- `kept_in` ("mailbox"; absent for a channel box, `ws_mail_box_kept_in()`), `room_id` (kept when the box moves to a channel, so it gets the same room back), `room_people`, `room_departments` (at most 200 and 50). Refused: the mailbox with nobody chosen, and before 11.0.188 ("The database has not been upgraded yet.").

## Who is in the room?
- The people chosen plus the members of the departments chosen, team accounts only (`ws_mail_room_wanted()`), put in step on every box save (`ws_mail_room_save()`) and every department save (`ws_mail_rooms_follow_department()`), with the room's folder and inbox reach following.
- An archived department named on the box keeps bringing its people until it is taken off the box, as its tasks keep it; the form lists it marked "(archived)". Nobody leaves the room by hand ("The people of a mailbox are chosen in its settings.").

## Who reads and answers?
- `ws_can_read_channel()`: members only, never opened for inspection. `ws_can_post_channel()` and `ws_can_manage_channel()` are false for a room, which closes every generic gate (messages, tasks, polls, API, MCP, agents, flows).
- Mail's own gates ask `ws_mail_can_answer()`: a channel by `ws_can_post_channel()`, a room by membership (team, room not archived). Used by New e-mail, Reply, Resend, status and holder, the pane's tools.

## Where does the room never show?
- Channel lists, the sidebar, pickers and `#` references, timeline, calendar, the two message searches, the API's channel lookup (`ws_api_channel_or_404()` answers not found), "Open channel" (refused), the first-channel check, the name-taken check.
- `tests/workspace_mail_room_hidden_test.php` lists every reader of `FROM|JOIN ws_channels` (file::function) with how it treats the kind: a new reader fails the test until it is classed.

## What is its face?
- The E-mails screen. `workspace.php?channel=<room>` redirects there (`ws_mail_room_redirect()`); notices lead to the conversation (`ws_mail_notice_url()`); the settings line says "Kept in the mailbox itself" and opens the box's list (`ws_mail_room_url()`). MA10b moves the list: change those two helpers.
- The pane shows no `#channel` and no Open in channel; New e-mail's Kept in names "The mailbox itself"; a known customer's line names the box, not a channel tag (`mail-routing.md`). Routes into channels stay.

## What else was chosen, and why? (worker, 2026-11-12, #8985)
- No AI triage for a room: its suggestion is a message under the card, which the room has no screen to show; the switch hides and saves off. Claude is never asked there.
- No `invited` notice to new room members: they were not invited to a channel.
- A new box kept in no channel starts as no support box (`mail-support-box.md`).
- Conversations kept in a former channel stay there when a box moves; only new mail follows the choice.

## What stays unverified?
- Making the room, the member sync, the form's show and hide, and the redirect ran in no browser or database in the cloud session; the tests check the pure parts and the wiring.
