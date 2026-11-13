---
name: look-page-widths
description: Look A's page widths (#8892): edit screens centred at 1120 px (760 + 24 + 336), lists as wide as their columns (1700 px to the window, details panel added), held while searching; tokens, classes, rail
---

## Which measures are there, and where?
- Owner decision #9070 (`workflow/decisions-made-panel-7.md`): lists from 1700 px (#9185); edit screens up to 1700 px wait for LW2 (`plans/look-widths-and-switch.md`).
- Tokens in their own block at the end of `assets/css/backend.src.css` ("Look A: page widths"): `--pg-measure-edit` 1120px, `--pg-measure-form` 760px, `--pg-measure-status` 336px, `--pg-measure-gap` 24px, `--pg-measure-list-min` 1700px, `--pg-measure-details` 20rem (details panel), `--pg-measure-rail` (the section rail and its gap).
- `tests/look_page_widths_test.php` pins the values, the rules and the screens that take them.

## How does a screen take them?
- With a class on `<body>` through `'extra classes'` of `pg_page_shell()`/`output_header()`: `pg-measure-edit` for an add or edit screen, `pg-measure-list` for a list.
- An edit screen also puts `pg-edit-columns` on the `.row` holding its form column (`order-1`), status column (`order-2`) and save bar (`order-3`).
- On the look so far: every add and edit screen with the form and status columns (#8934, `look-edit-screens.md`; `product_builder.php` also has `pg-edit-columns`); every root list screen on the list table driver (#8921: `view_*.php` but `view_fields.php`, the `erp_*.php` lists, `mail_queue.php`).

## How does an add or edit screen sit?
- `#content` is at most 1120 px plus its 1rem padding, centred. From lg (1024 px) the row is a grid: form column up to 760, status column up to 336, 24 between, the save bar across both. On a narrower window the form column gives way first; below lg they stack in source order as `edit-screen-wide-layout.md` says.
- A screen with a section rail (from 1280 px) centres the rail and the 1120 px content as one group, so the rail stays beside the form; the awaiting state books the same width.
- Field widths inside the cards are not changed here: they move with the add and edit screens' step (DL5c).

## How wide is a list?
- `#content` is `fit-content`: as wide as the list's columns (and the toolbar) need, at least 1700 px plus padding (1732) or the window when narrower, at most the window, centred. Report screens (`pg-measure-report`) read the same token.
- From 1024 px an open details panel adds its 20rem to that least width, up to the window (`#content:has(.pg-list-table-details:not([hidden]))`): the rows keep their closed width while the window has room. Before #9185 a short list's panel took its width from the rows (Calendars at 1920: 1278 to 958 px). Below 1024 px the panel is a sheet.
- Only the name column (the driver's first column) takes the slack: its inline width from the column spec is dropped (`width: auto !important`), so the table gives a wider box to it first; the other columns keep their spec widths. When the list is wider than the window, the name column is as wide as its content.
- The products list's default columns need about 2,350 px: it fills the window and scrolls sideways.
- The toolbar search above a list is 300 px (a phone keeps the toolbar's full-line rule).
- The vertical scrollbar of the rows' box counts in the width, so a short list's box can be about 15 px over 1732.
- While search words or a filter are set, the box keeps the width it had before them; before (#9017) each letter narrowed and re-centred it and the search box slid away. Half the page's scrollbar (about 8 px) still moves it when the rows no longer fill the window.
- How: the driver's `holdWidth()` (`pg_list_table.js`) sets `--pg-list-held` on `#content` before the rows first change: `heldWidth(width, panel)`, the box's width less an open panel. The stylesheet takes the larger of it and the measure and adds an open panel. It was an inline `min-width` before #9185, which kept a panel opened later from widening the box.

Related: docs/memory/ui/look-type-and-recipes.md
