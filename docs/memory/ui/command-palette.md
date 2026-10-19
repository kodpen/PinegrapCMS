---
name: command-palette
description: The panel search box (Ctrl+K, #SearchBox): where its quick-action chips come from, how typed text is matched, why every key must be a string (the "503" crash that emptied every search)
---

## Where do the chips come from?
- `pg_panel_backend_search()` (`includes/panel/search.php`) answers with `actions`: label, icon, url and `keys`. The empty query returns the actions only; `backend.src.js` caches them when the box opens and matches the typed text against `keys` with `actionMatches()` (prefix either way, Turkish letters folded by `normalizeTR()`).
- Settings sections come from the registry (`pg_settings_categories()`, `includes/settings/registry.php`); their `keys` are `pg_settings_keywords()` over one translated comma list per section.

## Why must every key be a string?
- `normalizeTR()` lower-cases each key. One number among them threw ("s.toLowerCase is not a function") and every typed search came back empty, chips and results alike.
- It started when the maintenance card listed "503" among its keywords: `pg_settings_keywords()` drops repeats through array keys, and PHP turns the key "503" into the integer 503. It now returns `strval`s (`tests/settings_keywords_test.php`), and `normalizeTR()` reads `String(s)` so a stray number cannot stop the matcher again.

## How does a settings chip open?
- In the dialog, after the search box has gone: see `settings-modal-navigation.md` (two modals at once leave a second backdrop).
