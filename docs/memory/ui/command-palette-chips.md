---
name: command-palette-chips
description: Which Ctrl+K search chips a viewer gets (the main-menu entry's gate), how typed text matches their keys, Enter, fast typing and reopening the box
---
## Which chips does a viewer get?
- The chips follow the main-menu gates: each one is offered under the rule of the `output_menu()` entry (`fn/output.php`) that opens the same screen, module switches included. `pg_panel_search_chip_gates()` (pure) takes the role, the rights and the query-backed rights read once for role 3 (`no_acl_check()`, `validate_contacts_access($user, true)`, `validate_calendars_access($user, true)`, an ad region) and returns one flag per group.
- Before, the chips were gated by hand (`role <= 2`, `role <= 1`, or nothing): a Manager got Menus (designers only), a Designer an empty Dynamic Regions list (administrators only), role 3 Pages, Files, Comments, Auto Dialogs, Visitors without the right, all "Access denied"; role 3 with manage_emails, calendars, manage_forms, ad regions or only the order-reports right never got those chips. Pinned by `tests/panel_search_chips_test.php`.

## How is a chip named and found?
- By the menu's name in its own key, not a shared short one: the states list is `lang('States/Provinces')` ("Eyaletler/İller"), the zones list `lang('Shipping Zones')` ("Nakliye Bölgeleri"); the shared `States`/`Zones` keys ("Şehirler", "Bölgeler") stay for other screens. The matcher reads only `keys`, so a chip's keys carry the menu's words: states `iller`, `eyaletler`, `province`; zones `nakliye bolge`, `shipping zone`, so "nakliye bölge" lists it beside Shipping Methods (whose key `nakliye` it also matches) (#4428, PR #4456; `tests/panel_search_shortcut_names_test.php`).
- The add chips read like the menu: `Create {var:1}` with the menu's noun (Page, Product, Page Style); the style list chip is `Page Styles`; Create Page Style opens `add_custom_style.php`, not the deprecated `add_style.php` redirect.

## How does typed text match a chip's keys?
- `actionMatches()` (`backend.src.js`): a key that starts with the query always matches; a query that starts with a key matches only when the key has four letters or more, equals the query or is followed by a space in it. Before, the short abbreviation keys (`ad`, `ip`, `kis`) matched every longer word: "adres" offered Ads, "ipek" four IP settings chips. Pinned in Node by `tests/panel_search_action_match_test.php`; `tests/settings_search_test.php` mirrors the rule in PHP.

## What do Enter and fast typing do?
- Enter in the box does nothing: the one-field form's `submit` is cancelled. Before, it reloaded the screen as `?query=…`, losing unsaved input.
- A first-page search aborts the request still running and every answer carries a number (`searchSeq`); an older one is dropped. Before, a query typed while one loaded was never sent and the old results stayed. Load More still waits for the running request.

## What does the box show when it opens again?
- The last query and its results, kept across close and reopen, with the query selected (the `shown` handler focuses the field and triggers `select`): Enter-free repeat of the last search, and the first key typed replaces it. Before, the caret sat at the end, so a new word was added to the old one ("pg-test-navayar", no results) (#8996, PR #9071). The clear button still empties the box.
