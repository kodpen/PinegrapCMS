---
name: mail-drafts-app-pill
description: The Drafts view names the application an e-mail draft was kept through (MT5b): the violet pill, where its name is read, "an assistant" for a deleted application
---

## Does a Drafts row say who kept it?
- Yes: a violet `pg-pill` "Kept by <application>" on the row's meta line, after the mailbox (`ws_mail_drafts_view_html()`, `includes/workspace/mail_drafts_screen.php`).
- None for a draft written in the panel, or by the person's own device session or OAuth connection: `draft_app_id` is 0 for them (`mail-api-html-files.md`).

## Where does the name come from?
- `ws_mail_drafts_view()` reads the names from `api_apps` for the rows' `draft_app_id` when the list is drawn, so a renamed application reads as it is called now. The id is stored, never a name (architect, channel 3377).
- `ws_mail_draft_app_label()`: the name; "an assistant" when the application's row is gone.
- Owner's yes to the column: channel poll 3369, result 3372.

## Tests?
- `tests/workspace_mail_drafts_screen_test.php` (`test_workspace_mail_drafts_screen_app_pill`).
