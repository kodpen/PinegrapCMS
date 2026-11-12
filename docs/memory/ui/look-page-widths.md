---
name: look-page-widths
description: Look A's page widths (#8892): edit screens centred at 1120 px (760 + 24 + 336), lists as wide as their columns (1280 px to the window); tokens, body classes, rail, name column
---

## Which measures are there, and where?
- Owner decision #8860 (`workflow/decisions-made-panel-7.md`). Tokens in their own block at the end of `assets/css/backend.src.css` ("Look A: page widths"): `--pg-measure-edit` 1120px, `--pg-measure-form` 760px, `--pg-measure-status` 336px, `--pg-measure-gap` 24px, `--pg-measure-list-min` 1280px, `--pg-measure-rail` (the section rail and its gap).
- `tests/look_page_widths_test.php` pins the values, the rules and the screens that take them.

## How does a screen take them?
- With a class on `<body>` through `'extra classes'` of `pg_page_shell()`/`output_header()`: `pg-measure-edit` for an add or edit screen, `pg-measure-list` for a list.
- An edit screen also puts `pg-edit-columns` on the `.row` holding its form column (`order-1`), status column (`order-2`) and save bar (`order-3`).
- On the look so far: `product_builder.php` (Create Product, Edit Product; not Variant Sets) and `view_products.php`. The others follow with their screen family (DL5).

## How does an add or edit screen sit?
- `#content` is at most 1120 px plus its 1rem padding, centred. From lg (1024 px) the row is a grid: form column up to 760, status column up to 336, 24 between, the save bar across both. On a narrower window the form column gives way first; below lg they stack in source order as `edit-screen-wide-layout.md` says.
- A screen with a section rail (from 1280 px) centres the rail and the 1120 px content as one group, so the rail stays beside the form; the awaiting state books the same width.
- Field widths inside the cards are not changed here: they move with the add and edit screens' step (DL5c).

## How wide is a list?
- `#content` is `fit-content`: as wide as the list's columns (and the toolbar) need, at least 1280 px plus padding or the window when narrower, at most the window, centred.
- Only the name column (the driver's first column) takes the slack: its inline width from the column spec is dropped (`width: auto !important`), so the table gives a wider box to it first; the other columns keep their spec widths. When the list is wider than the window, the name column is as wide as its content.
- The products list with its default columns needs about 2,350 px, so at 1920 and 1366 it still fills the window and scrolls sideways.
- The vertical scrollbar of the rows' box counts in the width, so a short list's box can be about 15 px over 1312.

Related: docs/memory/ui/look-type-and-recipes.md
