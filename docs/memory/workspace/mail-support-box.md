---
name: mail-support-box
description: A mailbox's "Support box" switch (WM14): what a plain box leaves out (status, holder, first-answer time, overdue filter, automatic answer), how lists count its conversations, the form's default
---

## What is it?
- Plan WM14 (owner 2026-11-11, #8765): a box says whether it is a support box, in `ws_mail_boxes.settings` as `support` (no column). `ws_mail_box_is_support()`: a box saved before the key existed is one, so today's boxes stay support boxes.
- Box form switch "Support box" (`ws_mail_support_field()`), before "First answer within (hours)"; the first-answer field and the "Automatic answer" half sit in a block shown only while it is on (`data-ws-mail-when="support=1"`, the form script reads a checkbox as its value when checked, '' when not).

## What does a plain box leave out?
- Status and holder: no badge on the card (`ws_mail_items_map()` gives each item `support`), in the list's row or the pane; no status or holder controls (`ws_mail_state_offer()` answers null when the item says `support` false).
- First-answer time: `ws_mail_first_reply_hours()` answers 0, so no "First answer by", nothing overdue; `ws_mail_first_reply_hours($box, true)` is the kept target the form shows.
- Automatic answer: `ws_mail_auto_reply_due()` is false.
- The AI suggestion names no status and nobody to give it to (`ws_mail_triage_body()` with no statuses); the summary and draft stay.

## How do the lists count its conversations?
- As open and held by nobody, whatever the columns say: `ws_mail_list_where()` takes `ws_mail_plain_box_ids()` (read once a request): status open adds `OR box_id IN (…)`, waiting, closed, held by me and overdue add `box_id NOT IN (…)`, held by nobody adds `OR box_id IN (…)`. Used by the E-mails list and the menu's unread count (`ws_mail_unread_count()`).
- The toolbar's Status, Held by and "First answer overdue" show only while a support box is among the reader's boxes (`ws_mail_list_support()`); the Mailbox filter stays.
- The external API keeps reading and writing the columns as they are: status, holder and the list's status filter there are data, not the screen's rule; `overdue` and `first_reply_due_at` follow the box (none for a plain box).

## What does a save keep?
- The switch as `support` true or false. A plain box keeps the target and the automatic answer it had, unchecked and hidden, for the day it is a support box again (`ws_mail_box_save_check()`).
- The columns keep moving: new mail opens a conversation, an answer sets waiting; the screens only stop showing them.

## Which default does a new box get?
- The switch starts on; the form's script turns it off while a new box is "Kept in" the mailbox itself and back on for a channel, until the person sets it (`data-ws-mail-new`). Without the script a new box keeps the switch as drawn.

## Tests?
- `tests/workspace_mail_support_test.php`: the switch, target and automatic answer, the offer, the list's conditions, rows and toolbar, the form, the suggestion and the wiring. Saving and the lists against a database, and the form in a browser, were not run in the cloud session.
