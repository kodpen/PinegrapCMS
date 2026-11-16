---
name: auto-dialog-browser-dialog
description: Auto dialogs on the site: the browser's <dialog> in place of jQuery UI, software.open_dialog() as its wrapper, the pick in the browser (cookie, sessionStorage), a page cached for all
---

## What does the page print?
- `get_page_content.php` (under `if (AUTO_DIALOGS)`): the enabled rows through `pg_auto_dialogs_for_page()` (web addresses only) and `pg_auto_dialogs_on_page($rows, $page_name)` (`includes/fn/auto_dialogs.php`): page blank or part of the page name, case-blind; id, url, width, height, delay, frequency as numbers, and the open settings (#9851, `auto-dialog-open-settings.md`).
- The same for every visitor but a cart row; printed with an empty list too while any row is enabled, so the visit start is kept from the first page.
- Nothing goes into the session: `visit_start_timestamp` is gone (PR #9805), so a site with an auto dialog is cached again (`page-cache-gate.md`).
- Preview `?preview_auto_dialog=<id>` (role < 3): one row, no delay, no cookie, as before.

## What does the script pick?
- `frontend.src.js` `software.init_auto_dialogs()` (and the hand-patched `frontend.min.js`): stops in an iframe and on body class `disable_auto_dialogs`; keeps the visit start (`sessionStorage` `pg_visit_start`, per tab).
- Owner (2026-11-15, poll 51): with the cookie notice on (`#pg-cc`), no dialog opens before the visitor has answered it: `start_auto_dialogs()` runs on the first `pg:consent` (sent on load with a stored answer and after each answer). With the notice off, or for a preview, it runs at once. Since PR #9852.
- `start_auto_dialogs()`: skips `disable_auto_dialog_<id>`; asks `popup_memory()`; waits `delay` minus the time since the visit's start, at least 1 s, or for scroll or leaving (#9851, `auto-dialog-open-settings.md`); `show_auto_dialog()` marks a dialog `s` when it opens and `c` when it closes.
- `popup_memory(remember)`: with marketing allowed or the notice off, the one cookie `pg_popup` (`PG_POPUP_COOKIE`, a year, `id:time:state` joined by `|`); a recurring dialog opens again after `frequency` hours; the old per-row cookie `software[auto_dialog_<id>]` (no longer written) still gives a last time. Otherwise `sessionStorage` `pg_popup`: each dialog once a visit.
- Both cookies are the notice's marketing row and its revoke names (`cookie-consent-catalogue.md`).

## How is the dialog drawn?
- `software.open_dialog({url, width, height, title, class_name, modal})`: a `<dialog class="pg-dialog …">` with a title bar, a 20px close button and the address in an iframe; `showModal()` (centred, page inert), `show()` with `modal: false`. Escape, the close button and a press on the backdrop close it; it is removed on close. Width and height as before (height + 25 for the bar; 75 % of the window on a mobile device). Returns the dialog, `false` for a non-web address.
- `software.dialog_style()` adds the rules once (`#pg-dialog-style`): the old frame's white box, `#c5c5c5` line, `0 0 20px #000` shadow, `.2em .2em 1em` padding, grey backdrop. No `ui-dialog` classes; themes' `.software.ui-dialog` rules no longer reach it. Not draggable or resizable any more.
- While the notice's card is open (reopened by the visitor), a modal dialog waits for `pg:consent` and opens a tick later: a modal dialog would make the notice inert. A waiting dialog is not marked shown.
- jQuery UI still draws the edit-region, share-comment and kiosk dialogs.

## Tested?
- `tests/auto_dialog_form_test.php` (the consent wait and the one cookie, rows the same for every visitor, no session or cookie in the page block, the script's pick and the dialog in both scripts), `tests/web_address_test.php`, `tests/page_cache_test.php`. Before and after pictures of an old row on a sandbox site: same box (400 x 675, iframe 392.8 x 620.4).
