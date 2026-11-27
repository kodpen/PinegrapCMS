---
name: translations-screen-selection
description: Translations screen selection (TS3): a tick on every row, "Select all on this page", the bar with Approve, Translate again, Keep as in the source and approve, Clear, and the "Translate again" dialog
---

## What can be ticked?
- Every row, a reviewed one too (before TS3 a reviewed row had no tick). "Select all on this page" (`#pg_tr_pick_all`) above the rows ticks the 100 texts shown; with some ticked its box shows a dash (indeterminate).

## What is the selection bar?
- `#pg_tr_selbar` at the foot of the list (`.pg-tr-selbar-wrap`, sticky at the window's lower edge), hidden until a row is ticked: "{var} selected", "Approve", "Translate again", "Keep as in the source and approve" (small quiet buttons), "Clear" (small ghost), then × "Take the ticks off". 48 px or more, radius 14, an accent line.
- At 767 px and under "Approve" and "Translate again" are icons; "Keep as in the source and approve" and "Clear" are under the bar's "More".
- "Keep as in the source and approve" no longer stands above the list; each action changes the rows in place (no reload) and takes the ticks off.

## What does each action do? (`translations_action.php`)
- "Approve" (`approve`, `pg_tr_screen_approve()`): the ticked machine translations turn reviewed at once, no dialog. A pending or reviewed text is left; the suspicious flag stays, as with the eye button.
- "Keep as in the source and approve": today's dialog and wording (`#pg_tr_accept_modal`, `accept_source`); the answer now names the ids written (`pg_tr_accept_source()['ids']`).
- "Clear" (`clear` with `string_ids`, `pg_tr_screen_clear()`): dialog "Clear translations" with "Delete the reviewed translations as well"; the machine ones of the ticked texts are deleted, the reviewed ones only with the tick. The rows turn pending. Without `string_ids` `clear` is still the scope's "Clear translations" of "More".

## What does "Translate again" do?
- From the bar or a row's "Details" (a row with a translation). Dialog: "{var} text(s) will be sent to {engine} again.", "Replace the reviewed ones as well" (off; disabled with no reviewed text ticked).
- `again` → `pg_tr_screen_translate_again()`: the reviewed ones are left out unless ticked (`pg_tr_screen_again_split()` with `pg_tr_reviewed_kept_status()`, the rule of `pg_tr_reviewed_kept()`); the rest lose their translation (`pg_tr_screen_clear()`) and a job of the language's engine opens with them (`pg_tr_job_open()`, shared with `pg_tr_update()`), filed under the screen's scope.
- Why delete first: a job sends only texts with no translation (`pg_tr_job_skip_translated()`). Until the engine answers the page shows the source text; a text that fails stays pending for "Translate pending". The dialog says so.
- The screen follows the job as it follows "Translate pending" (`prepareJob()`, `followJob()` in `translations.js`; a browser engine's translator is made in the dialog's click). With "By hand" or an engine not ready the bar's button is disabled with the reason and "Details" has no "Translate again".

## Where is it tested?
- `tests/translations_screen_test.php` (`pg_tr_reviewed_kept_status()`, `pg_tr_screen_again_split()`), `tests/translations_screen_bugs_test.php` (source checks of the bar and the in-place actions).

Related: docs/memory/ui/translations-screen-row.md, docs/memory/ui/translations-screen-drawing.md, docs/memory/ui/translations-screen-frame.md
