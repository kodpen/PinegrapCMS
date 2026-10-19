---
name: command-palette
description: Panel search box (Ctrl+K): where the chips come from, how text is matched, why every key must be a string (the "503" crash), chips that open a dialog
---

## Where do the chips come from?
- `pg_panel_backend_search()` (`includes/panel/search.php`) answers with `actions`: label, icon, url and `keys`. The empty query returns the actions only; `backend.src.js` caches them when the box opens and matches the typed text against `keys` with `actionMatches()` (prefix either way, Turkish letters folded by `normalizeTR()`).
- Settings sections come from the registry (`pg_settings_categories()`, `includes/settings/registry.php`); their `keys` are `pg_settings_keywords()` over one translated comma list per section.

## Why must every key be a string?
- `normalizeTR()` lower-cases each key. One number among them threw ("s.toLowerCase is not a function") and every typed search came back empty, chips and results alike.
- It started when the maintenance card listed "503" among its keywords: `pg_settings_keywords()` drops repeats through array keys, and PHP turns the key "503" into the integer 503. It now returns `strval`s (`tests/settings_keywords_test.php`), and `normalizeTR()` reads `String(s)` so a stray number cannot stop the matcher again (#590).

## How does a chip open a dialog instead of a screen?
- A settings chip opens the settings after the search box has gone: see `settings-modal-navigation.md` (two modals at once leave a second backdrop).
- The "Keyboard Shortcuts" chip (keys klavye, kısayol, keyboard, shortcut) has the address `#keyboard-shortcuts`; the chip handler opens `window.pgShowShortcuts()` in the same `hidden.bs.modal` way (`account-menu.md`).
