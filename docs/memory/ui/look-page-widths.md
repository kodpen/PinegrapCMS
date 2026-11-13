---
name: look-page-widths
description: Look A's page widths: edit screens centred at 1700 px (1300 + 24 + 376), lists as wide as their columns (1700 px to the window, details panel added), held while searching; tokens, rail
---

## Which measures are there, and where?
- Owner decision #9070 (`workflow/decisions-made-panel-7.md`): lists from 1700 px (#9185); edit screens up to 1700 px (#9269). Edit screens were 1120 px before.
- Tokens in their own block at the end of `assets/css/backend.src.css` ("Look A: page widths"): `--pg-measure-edit` 1700px, `--pg-measure-form` 1300px, `--pg-measure-status` 376px, `--pg-measure-gap` 24px, `--pg-measure-list-min` 1700px, `--pg-measure-details` 20rem (details panel), `--pg-measure-rail` (the section rail and its gap).
- `tests/look_page_widths_test.php` pins the values, the rules and the screens that take them.

## How does a screen take them?
- With a class on `<body>` through `'extra classes'` of `pg_page_shell()`/`output_header()`: `pg-measure-edit` for an add or edit screen, `pg-measure-list` for a list.
- An edit screen also puts `pg-edit-columns` on the `.row` holding its form column (`order-1`), status column (`order-2`) and save bar (`order-3`).
- A page with no status column adds `pg-measure-single`: `#content` is the form column wide (1300 px, a rail added), centred.
- On the look so far: every add and edit screen with the form and status columns (#8934, `look-edit-screens.md`; `product_builder.php` also has `pg-edit-columns`); every root list screen on the list table driver (#8921: `view_*.php` but `view_fields.php`, the `erp_*.php` lists, `mail_queue.php`); settings, tool and import pages (`look-screen-widths.md`).

## How does an add or edit screen sit?
- `#content` is at most 1700 px plus its 1rem padding, centred. From lg (1024 px) the row is a grid: form column up to 1300, status column 376, 24 between, the save bar across both. On a narrower window the form column gives way first; below lg they stack in source order as `edit-screen-wide-layout.md` says.
- A screen with a section rail (from 1280 px) centres the rail and the 1700 px content as one group, so the rail stays beside the form; the awaiting state books the same width.
- Fields keep the 12-column grid and widen with the form column; a card stays one per row. Owner (2026-11-13, #9271): the measure alone; two cards abreast is the way back if stretched fields look wrong (a new question); fields side by side at content width was turned down.

## How wide is a list?
- `#content` is `fit-content`: as wide as the list's columns (and the toolbar) need, at least 1700 px plus padding (1732) or the window when narrower, at most the window, centred. Report screens (`pg-measure-report`) read the same token.
- From 1024 px an open details panel adds its 20rem to that least width, up to the window (`#content:has(.pg-list-table-details:not([hidden]))`): the rows keep their closed width while the window has room. Below 1024 px the panel is a sheet.
- Only the name column (the driver's first column) takes the slack: its inline width from the column spec is dropped (`width: auto !important`), so the table gives a wider box to it first; the other columns keep their spec widths. When the list is wider than the window, the name column is as wide as its content.
- The products list's default columns need about 2,350 px: it fills the window and scrolls sideways. A box's own scrollbar counts in its width (about 15 px over 1732).
- The toolbar search above a list is 300 px (a phone keeps the toolbar's full-line rule).
- While search words or a filter are set, the box keeps the width it had before them (#9017).
- How: the driver's `holdWidth()` (`pg_list_table.js`) sets `--pg-list-held` on `#content` before the rows first change: `heldWidth(width, panel)`, the box's width less an open panel. The stylesheet takes the larger of it and the measure and adds an open panel. It was an inline `min-width` before #9185, which kept a panel opened later from widening the box.

Related: docs/memory/ui/look-type-and-recipes.md
