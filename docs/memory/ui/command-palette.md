---
name: command-palette
description: Panel search box (Ctrl+K): where the chips come from, how text is matched, why every key must be a string (the "503" crash), chips that open a dialog
---

## Where do the chips come from?
- `pg_panel_backend_search()` (`includes/panel/search.php`) answers with `actions`: label, icon, url and `keys`. The empty query returns the actions only; `backend.src.js` caches them when the box opens and matches the typed text against `keys` with `actionMatches()` (prefix either way, Turkish letters folded by `normalizeTR()`, which calls `window.pgFoldTR()`).

## What is pgFoldTR()?
- One shared fold at the top of `backend.src.js`: `String(s).toLowerCase()`, the U+0307 that a lowercased capital İ keeps removed, ı, ğ, ü, ş, ö, ç to their plain letters. The palette, the Site Settings "Search in this page" filter (`initSettingsFilter()`) and the API applications filter (`api_settings.php`) use it. Before, a query starting with a capital İ ("İade", or a phone keyboard's capital first letter) matched no chip, and "denetim izi" hid the "Denetim İzi" settings card. Pinned by `tests/panel_turkish_fold_test.php`. Comboboxes fold through Bootstrap's `searchNormalize` instead (`pg-combobox.md`).
- Settings sections come from the registry (`pg_settings_categories()`, `includes/settings/registry.php`); their `keys` are `pg_settings_keywords()` over one translated comma list per section.

## Why must every key be a string?
- `normalizeTR()` lower-cases each key. One number among them threw ("s.toLowerCase is not a function") and every typed search came back empty, chips and results alike.
- It started when the maintenance card listed "503" among its keywords: `pg_settings_keywords()` drops repeats through array keys, and PHP turns the key "503" into the integer 503. It now returns `strval`s (`tests/settings_keywords_test.php`), and `normalizeTR()` reads `String(s)` so a stray number cannot stop the matcher again (#590).

## How does a chip open a dialog instead of a screen?
- A settings chip opens the settings after the search box has gone: the chip hides the search dialog and opens the settings in its `hidden` handler (`pgUI.dialog.on(…, 'hidden', …, {once: true})`, `settings-modal-navigation.md`).
- The "Keyboard Shortcuts" chip (keys klavye, kısayol, keyboard, shortcut) has the address `#keyboard-shortcuts`; the chip handler opens `window.pgShowShortcuts()` in the same `hidden` way (`account-menu.md`).

## Where does focus go on Load More?
- Load More (the next 20 results) is removed while the page loads; if it held the focus, the result above it takes it first, so focus never falls to `<body>` (`focus-after-removal.md`). The spinner is `role="status"` with a `.visually-hidden` `t('Loading...')`. When the page arrives, focus moves to its first result (`appendResults()` returns it), or to the new Load More when the page brought none.
- A later page that fails, or answers anything but success, goes to `loadMoreFailed()`: `currentOffset` goes back by 20, a `role="alert"` `t('An error occurred.')` line and a new Load More (focused) come back. Before, the spinner went and nothing replaced it, so the rest of the results could not be reached until the query changed. A click while a page loads is ignored. Pinned by `tests/list_page_state_test.php`.
