---
name: mail-resolve-by
description: The resolve-by time of a support-box conversation (help centre HC6): the box's "Resolve within (hours)", how due and overdue are computed, where the Incoming e-mail screen shows it, what it leaves out
---

## What is it?
- A support box may set when a conversation is due to be closed: settings JSON key `resolve_hours` (no column), box form field "Resolve within (hours)" beside "First answer within (hours)" in the Answers tab, shown only while "Support box" is on. 0 or empty is none; at most `WS_MAIL_RESOLVE_MAX` (2,160 hours, 90 days).
- Read by `ws_mail_resolve_hours($box, $kept)` (a plain box answers 0; `$kept` gives the target its form keeps), checked by `ws_mail_resolve_input()`, saved by `ws_mail_box_save_check()` as the first-answer target is (`mail_status.php`, `mail_settings.php`).

## How is it computed?
- `ws_mail_resolve_due()`: `first_in_at` + target while the conversation is not closed. Nothing is stored. The architect settled (2026-11-27, `workspace/support-requests.md`): no "Solved" state, no change of the `status` ENUM, and the clock does not stop while waiting for the customer.
- An answer does not stop it either; a closed conversation opened again by a new mail keeps its first mail's time, so it may come back already overdue.
- `ws_mail_resolve_overdue()` is past due; `ws_mail_resolve_html()` draws "Resolve by …" (hourglass icon) or the red badge "Resolve overdue".

## Where is it shown?
- Communication › Incoming e-mail only: the list row's state (`ws_mail_status_html()`'s fifth argument, passed by `ws_mail_list_rows_html()` from the row's `resolve_hours`) and the reading pane's head (`ws_mail_pane_resolve_html()`, `ws_mail_pane()` gives `resolve_hours`).
- Not on the channel card, the dashboard card's count, the API or the list's "First answer overdue" filter; no notice or rule action reads it.

## Tests?
- `tests/workspace_mail_status_test.php` (`test_workspace_mail_resolve_target`), the drawer's field lists in `tests/workspace_mail_drawer_test.php`. The save and the screen against a database and in a browser were not run in the cloud session.
