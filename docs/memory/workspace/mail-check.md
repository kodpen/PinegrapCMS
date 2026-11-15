---
name: mail-check
description: Check e-mail on Communication › Incoming e-mail (IB2, #9481): which boxes a press reads, the fetch's lock and budget, the 15-second gap, presses a minute, the line over the list
---

## What does Check e-mail do?
- A toolbar button (`data-ws-mail-check`, beside Mark all as read, `ws_mail_list_filters_html()` `$can_check`) reads now the IMAP boxes the person reads: on, `transport = 'imap'`, a `host`, and the box's channel or room or one of its routes' channels readable by `ws_can_read_channel()` (`ws_mail_check_boxes()`, `ws_mail_check_reads_box()`).
- A box's room has no screen of its own (`mail-room.md`): its face is this list narrowed by `box`, and there the button reads that box only (`data-ws-mail-check="<box id>"`).
- Drawn only when such a box exists and the site is no demo (`pg_demo()`). Module `includes/workspace/mail_check.php`; panel action `ws_mail_check` (`box_id`, the list's filters, `conversation`).

## Which limits hold?
- Each box goes through `ws_mail_fetch()` (`mail-fetch.md`): its `GET_LOCK` (a box the job or another press holds counts as waiting) and its 15 s budget per box; a press spends at most `WS_MAIL_CHECK_BUDGET` (30) s, and a box that cannot have its whole budget waits.
- A box read in the last `WS_MAIL_CHECK_GAP` (15) s, by the job or a press, is not read again (`ws_mail_check_due()`).
- `WS_MAIL_CHECK_PER_MINUTE` (4) presses a minute per person: `waf_rate_record('u:<id>', 'wsmailchk', 60)`, fails open when the counter cannot be written. Refused: "You checked for e-mail several times this minute. Try again in a minute."
- A demo site reads nothing ("A demonstration site reads no mailbox."). No schema.

## What does the list say after it?
- `ws_mail_check_line_html()` in `[data-ws-mail-check-line]` (role status) under the toolbar: "3 new e-mails", "1 new e-mail" or "No new e-mail"; each failed box as "<name>: " and the words of its Mailboxes card (`ws_mail_error_text()`); a waiting box adds "Some mailboxes are being read already; their e-mail comes in a moment."
- Architect (2026-11-14): when no box could be read (`read` 0, a failure, nothing stored), the count line is not written, only the failures; with a box read and one failed, both stand. A box read in the last 15 s counts as read.
- While the request runs (a read can hold it for many seconds) the button is disabled, `aria-busy`, with a spinner and "Checking e-mail…"; the button's Turkish is "E-postaları kontrol et", the owner's verb (architect, channel 2941).
- When mail came, the answer carries the list drawn again with the address's filters (`ws_mail_list_html()`), the open conversation still marked.

## Tests?
- `tests/workspace_mail_check_test.php`: readable boxes, the gap, the press limit, the texts and line, the action, the button and the script. The read against a mail server was not run (no port 993 in the cloud sessions).
