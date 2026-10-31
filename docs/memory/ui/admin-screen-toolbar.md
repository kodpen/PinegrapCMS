---
name: admin-screen-toolbar
description: Panel screen basics (frame, notices, fields, width, icons, help), the one-toolbar principle with view_folders.php as reference, slot order, classes and the pg_screen_toolbar() helper.
---

## What are the basic panel UI helpers and conventions?
- Bootstrap 6 (panel; sites stay on 5.3.8). `output_header([...])` / `output_footer()` = page frame. Cards are spaced with bottom margins on the v6 spacer scale: v5's `mb-5` (3rem) is `mb-12`; most screens use `mb-7` / `mb-5` (1.5rem / 1rem).
- `$liveform->output_errors()` / `output_notices()` print messages, `add_notice()` / `add_error()` add one, `mark_error('field', 'message')` marks a field, then `go($url)`. A screen that adds notices prints them and calls `remove_form()` after drawing (`liveform-notices-trap.md`).
- Fields: `$liveform->output_field([...])`, never a hand-written `<input>` (error marking, refill).
- No `max-width` on an admin screen: screens are `container-fluid` wide; a field's width comes from the grid (`col-*`, `md:col-*`).
- Icons: Bootstrap Icons `<i class="bi bi-…" aria-hidden="true"></i>`; Material Icons only in old code and the main menu (`main-menu-icons.md`).
- Reference screens: `view_folders.php` (toolbar), `product_builder.php` (edit screen layout, `edit-screen-wide-layout.md`). Help link: `panel-help-links.md`.

## What is the admin screen design principle?
- As `view_folders.php` (its tree panel aside): everything a screen does is in ONE toolbar straight under the title (never in a `.row`/`.col`), content below. Owner decision 2026-10-30 (`workflow/decisions-made-product-14.md`).
- Nothing alone under the title: a lone action joins the bar, a fact goes into the grow slot, no second heading repeating the title. Back only by arrow + breadcrumb (`back-arrow-no-copies.md`). Filters, sort, view at the right end, never a second column. No sticky bar (#3837).
- Left: `mass_edit.php` still wraps its bar in a column; move it when next touched (`page-button-bar.md`).

## What is the toolbar slot order?
| Slot | What | Class |
|---|---|---|
| 1 | Primary action (often opens a menu: "Yeni", "Kaydet") | `btn btn-sm btn-solid theme-primary rounded-pill px-5` |
| 2 | Secondary tools, grouped into one menu if needed | `btn btn-sm btn-text theme-secondary` |
| 3 | Location / breadcrumb, flexes (`flex: 1 1 220px`, `.pg-toolbar-grow`) | - |
| 4 | Search / filter | `input-group input-group-sm rounded-pill` (`.pg-toolbar-search`) |
| 5 | View switcher (if any) | `btn-group btn-group-sm` + `btn-text theme-secondary active` |
| 6 | Overflow menu `⋮`: rare, screen-specific things | `btn btn-sm btn-text theme-secondary` with `data-bs-placement="bottom-end"` + `ul.menu.pg-menu-end` |
- A menu slot is `div.pg-menu-wrap` > toggle (`data-bs-toggle="menu"`, `data-bs-display="static"`) + `ul.menu` of `.menu-item`s.
- The strip under the heading of older screens (`#button_bar`) is the same component: `page-button-bar.md`.

## How does a screen draw its toolbar?
- `pg_screen_toolbar($slots, $opts)` (`includes/fn/output.php`) returns `nav#button_bar.pg-toolbar` with slots `primary`, `secondary`, `grow`, `search`, `view`, `overflow`, always in that order; `''` when no slot is filled, so no empty bar. Options: `label` (aria name, default "Button Bar"), `class`.
- CSS hides a bar only when empty: no `.btn`, `a`, `button`, `select`, visible `input`, `textarea`, filled grow. Tabs, a `pg_filter_select()` (Apply in `<noscript>`) or a grow fact keep it (#4070, `tests/button_bar_hidden_test.php`).
- A slot is markup (a menu, `pg_filter_select()`), a button array `label`/`url`/`icon`/`id` (escaped, drawn in the slot's recipe above) or a list of both. A right cluster with no grow gets an empty grow as spacer. Pinned by `tests/screen_toolbar_test.php`.
- `pg_page_shell()` draws no buttons: its `actions`/`primary_button`/`action_bar_class` keys and the `.pg-page-actions` row were removed; `tests/panel_screen_titles_test.php` refuses the keys.

Related: docs/memory/ui/admin-screen-toolbar-rules.md
