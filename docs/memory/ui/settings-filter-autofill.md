---
name: settings-filter-autofill
description: Settings category screens: a password manager wrote the saved username ("admin") into the toolbar filter and every section card vanished; the token and the focus guard that stop it (PR #1700)
---

## What was the symptom?
- `settings_security.php` (any `settings_*.php`, all drawn by `includes/settings/screen.php`) opened from the main menu: 1-2 s after load every section card got `d-none`; only Save and the footer stayed. The toolbar search box showed "admin", the saved sign-in name.
- The settings modal never showed it: it has no filter box, and its text fields are held read-only until touched (`settings-modal-autofill-lock.md`).

## Why did it happen?
- The filter `#pg_settings_filter` sits in the toolbar, outside `$pg_settings_body`, so the pass that stamps `autocomplete="pg-no-autofill"` on body controls never reached it; it kept `autocomplete="off"`, which Chrome ignores.
- The password manager filled it and raised a trusted `input` event, the same as a keystroke. `initSettingsFilter()` (`assets/js/backend.src.js`) hid every card whose text lacked "admin".

## What stops it now?
- The box carries `autocomplete="pg-no-autofill"` itself.
- `initSettingsFilter()` filters only while the box has focus: a value that arrives while `document.activeElement` is not the box (a fill, or a value restored before the script ran) is wiped and all cards shown. Typing, the clear button and Escape all happen with focus, so nothing a person does is lost.
- The workspace MCP tools filter (`data-ws-mcp-filter`, `includes/workspace/mcp.php`), on a page with password fields, got the same token and guard.

## What about a new filter box?
- A live filter that hides content must ignore a value it did not get from a focused box, and must not rely on `autocomplete="off"`. Search boxes that only submit a GET form (ERP lists, logs, translations) only show the stray text; they were left as they are.
