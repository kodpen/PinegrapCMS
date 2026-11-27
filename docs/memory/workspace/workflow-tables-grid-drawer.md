---
name: workflow-tables-grid-drawer
description: A Data table's grid (workspace_wtables.php?id=N, DM2): one-line cells, widths by type, the box with its head, "#" and totals held in view; the row and New row in one form drawer
---

## How is a cell drawn?
- One line: `ws_wtable_grid_cell_html()` puts the text in a `div.ws-wt-cell` cut with an ellipsis, the whole text in its `title` (line breaks kept there); a text's line breaks show as spaces in the grid.
- Width by the type the column shows (`ws_wtable_grid_shape()`; a lookup or rollup gives the type of the column it reads): number, money, a date without time and checkbox narrow (9 rem at most); text and list wide (10 to 24 rem); formula, status, person, links and a date with time in between (14 rem).
- Number, money and formula stand right; every `.text-end` cell of the grid, the totals included, has tabular figures.
- A formula's error shows `#CODE` in red, the code in the title.

## How does the grid scroll?
- `div.ws-wt-grid` (`role="region"`, focusable, named "Rows") scrolls both ways inside the card, at most the window's height less the bar (20 rem at least). The head row (`thead th`) and the totals (`tfoot`) are sticky at its top and bottom, the "#" column at its start; the same on a phone.
- The rules are `ws_wtable_grid_style()`, a `<style>` printed with the grid on this screen only, in `@layer custom` with `--pg-*` tokens: `backend.src.css` was held by open PRs when it was written. Moving them there later changes nothing drawn.

## How does a row open?
- A click on a row (`tr[data-ws-wt-open]`, not on its links or buttons, not a click ending a text selection) goes to `?id=N&row=M…#ws-wt-row-form`; on a cell changed in the grid it waits for a second click first (`workflow-tables-grid.md`); the keyboard has the same address on the row's number (`a.ws-wt-open`, "Row N"). The pencil of the row is gone; its Delete is in the row's "⋯" menu in the last column (`workflow-tables-grid-order.md`).
- The page opens the drawer the hash names (`openFromHash()`, the bar's drawers' code). A drawer closed takes its hash off the address (`history.replaceState`), so the same link opens it again.

## What is the row drawer?
- `ws_wtable_grid_row_drawer()`: `dialog#ws-wt-row-form.ws-wt-drawer`, the form wrapping header, body and footer (`d-contents`); the fields as before, in the columns' order; a text or list field as tall as its value: `ws_wtable_grid_text_rows()` (56 letters a line, 2 to 30 rows) from the server, then grown to its whole text at the drawer's width when it opens and as it is typed in (the phone's drawer holds fewer letters a line).
- Footer: Delete (`pg-button-danger`, the row's confirm through `.ws-wt-action`) at the left of an existing row, Save or Add at the right; no Cancel, the cross or Escape closes it.
- New row (the bar's button) opens the same drawer empty, in place when the page holds the new row form (`data-ws-wt-new`), else by going to the table's address with the hash.
- The post is unchanged (`row_save`, `revision`); a refusal comes back to `#ws-wt-row-form` with the typed values and the refused cells named, and `changed_meanwhile` shows the row as it is now, as before.

## What pins it?
- `tests/workspace_wtable_grid_test.php`.
