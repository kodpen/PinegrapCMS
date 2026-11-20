---
name: auto-dialog-browser-dialog
description: Auto dialogs on the site: the browser's <dialog> in place of jQuery UI, software.open_dialog() as its wrapper, the pick in the browser (cookie, sessionStorage), a page cached for all
---

## What does the page print?
- `get_page_content.php` (under `if (AUTO_DIALOGS)`): the enabled rows through `pg_auto_dialogs_for_page()` (web addresses only) and `pg_auto_dialogs_on_page($rows, $page_name)` (`includes/fn/auto_dialogs.php`): page blank or part of the page name, case-blind; id, url, width, height, delay, frequency, the open settings (`auto-dialog-open-settings.md`).
- Printed into the inline script by `pg_auto_dialogs_script_json()`, `<` `>` `&` as `\u` escapes: a URL holding `<!--<script>` (the field refuses only spaces) made the parser swallow the rest of every page (bug hunt, agent channel message 10243).
- The same for every visitor but a cart row; printed with an empty list too while any row is enabled, so the visit start is kept from the first page.
- Nothing goes into the session (`visit_start_timestamp` gone, #9805): the page is cached (`page-cache-gate.md`).
- Preview `?preview_auto_dialog=<id>` (role < 3): one row, no delay, no cookie.

## What does the script pick?
- `frontend.src.js` `software.init_auto_dialogs()` (and the hand-patched `frontend.min.js`): stops in an iframe and on body class `disable_auto_dialogs`; keeps the visit start (`sessionStorage` `pg_visit_start`, per tab).
- Owner (2026-11-15, poll 51): with the cookie notice on (`#pg-cc`), no dialog opens before the visitor has answered it: `start_auto_dialogs()` runs on the first `pg:consent` (sent on load with a stored answer and after each answer). With the notice off, or for a preview, it runs at once. Since PR #9852.
- `start_auto_dialogs()`: skips `disable_auto_dialog_<id>`; asks `popup_memory()`; waits `delay` minus the time since the visit's start, at least 1 s, or for scroll or leaving (#9851, `auto-dialog-open-settings.md`); `show_auto_dialog()` marks a dialog `s` when it opens and `c` when it closes.
- `popup_memory(remember)`: with marketing allowed or the notice off, the one cookie `pg_popup` (`PG_POPUP_COOKIE`, a year, `id:time:state` joined by `|`); a recurring dialog opens again after `frequency` hours; the old per-row cookie `software[auto_dialog_<id>]` (no longer written) still gives a last time. Otherwise `sessionStorage` `pg_popup`: each dialog once a visit.
- Both cookies are the notice's marketing row and its revoke names (`cookie-consent-catalogue.md`).

## How is the dialog drawn?
- A designed pop-up (`content` component, #10450) is drawn from a `<template>` instead: `auto-dialog-content.md`.
- `software.open_dialog({url, width, height, title, class_name, modal, opened})`: a `<dialog class="pg-dialog …">`, title bar, close button, the address in an iframe; `showModal()`, or `show()` with `modal: false`. Escape, the close button and the backdrop close it; removed on close. Height + 25 for the bar; 75 % of the window on a mobile device. Returns the dialog, `false` for a non-web address.
- `software.dialog_style()` adds the rules once (`#pg-dialog-style`): the old frame's white box, grey line, shadow and backdrop. No `ui-dialog` classes, so themes' `.software.ui-dialog` rules no longer reach it; not draggable or resizable.
- While the notice's card is open (reopened by the visitor), a modal dialog waits for `pg:consent` and opens a tick later (it would make the notice inert). `open_dialog()` then calls `properties.opened(dialog)`, where `show_auto_dialog()` marks and counts it; before the hunt of message 10243 the waiting one was never marked and opened again on every page.
- jQuery UI still draws the edit-region, share-comment and kiosk dialogs.

## Tested?
- `tests/auto_dialog_form_test.php` (consent wait, one cookie, rows the same for all, the script in both files), `tests/auto_dialog_script_test.php` (`show_auto_dialog()` and `open_dialog()` of both scripts run in Node: the waiting dialog, the escaped rows), `tests/web_address_test.php`, `tests/page_cache_test.php`.
