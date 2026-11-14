---
name: messages-area-mail-tab
description: Communication › Incoming e-mail (MA10b #9453): the tab and its gate, the moved list and pane, the door from workspace_mail.php, the menu row and its unread badge, what MA10b2 and MA10c add
module: messages_area
---

## What is the tab?
- `messages_mail.php`, the first tab of Communication: the e-mail list and the reading pane the Workspace's E-mails screen had, moved, not copied (`workspace/mail-list.md`, `workspace/mail-screen-pane.md`). Its rows, filters and Close link name `messages_mail.php` (`ws_mail_list_page_url()`).
- Drawn like `pg_messages_tab_page()` (heading "Communication", the strip, the container's gutter), with the Workspace shell and script inside (`ws_screen_assets($viewer, 'mail', …)`), body classes `workspace workspace_mail messages-area`.
- Owner (2026-11-12, #8961, `workflow/decisions-made-workspace-21.md`): the incoming e-mail sits in Communication, not among the Workspace's channels.

## Who sees it?
- Gate `mail` of `pg_messages_gate_allows()`: `ws_mail_list_visible()` only, not the campaigns' right (`plans/messages-area-decided-2.md`). Somebody who reads a mailbox without campaign rights gets the Communication row with this tab alone.
- `pg_messages_mail_visible($user)`: the Workspace on and installed, the person on its team, a box writing where they read; asked once a request, the Workspace's files loaded only when it is on. MA10c moves it to `ws_mail_on()`.
- The screen itself: `ws_screen_gate()`, `ws_mail_reads_ready()`, then `ws_mail_list_visible()` ("Access denied" otherwise).

## Where do the old addresses lead?
- `workspace_mail.php` is a door (`pg_messages_mail_door()`: the address's plain words kept, a list dropped). `ws_mail_room_url()` names the tab, so inbox lines, a room's redirect from `workspace.php` and the card's Open e-mail land there.
- The Workspace's side list lost its E-mails tile and `ws_side_screen()` no longer knows `workspace_mail.php`; `output.php` lights row 9 for `messages_mail.php`.

## Where is the unread number?
- On row 9's badge (`pg_messages_mail_unread()` = `ws_mail_unread_count()`), counted when the person may open the tab; the screen's script no longer finds a tile to update, so the badge changes on the next page.

## What comes next?
- MA10b2: an HTML body for a written e-mail. MA10c: mail with the Workspace off (`plans/messages-area-steps-2.md`).
- The list and pane keep the Workspace's look; the move did not redraw them to look A.

Tests: `tests/messages_area_test.php`, `tests/workspace_mail_list_test.php`, `tests/workspace_mail_card_test.php`.
