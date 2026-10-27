---
name: widget-lists-editor
description: Lists inside a record in the designer (#1688): palette items, where one may land, the list panel, Bind Data row values, canvas band and fade, the JS twins and their test.
---

## How does an operator add a list?
- Palette category "Lists in a record": Values of a field, Parts of a field split at commas, Attachments. Each lands as a `loop_area` with `props.source` around one bound row (`_sdListStarter()`): a badge bound to `__value_text`, or a link (`href` `__file_url`, text `__file_name`, `target` `_blank`). A values or parts list lands without its field: the band and the panel ask for it.

## Where may a list land?
- `_sdListPlaceOk()` in `canDrop()`, before the root's free pass: inside a widget whose kind offers the source (`_sdKindLists()`), in a form list inside the main loop (outside it no record fills the list), never inside itself, at most four lists deep (`SW_LIST_MAX_DEPTH`, as the site draws). A list of a source the editor does not know moves only inside its own widget.
- A list is not the main loop: the one-loop rule, delete and copy protection (`_subtreeContainsLoopArea()`), the canvas loop hiding of single-record kinds, `_sdFirstLoopArea()`, the comments e-mail walk and the ghosts skip it. The main loop never goes into a list.

## What does the list panel set?
- `propsListArea()` (from `propsLoopArea()`): what it lists, the field (only fields that give that source rows: `_sdListFieldFits()`; an attachments list may name none), rows to show (0-100, 0 every row), row order (stored, last first, A to Z, Z to A). `_sdListWrite()` keeps empty values out of the tree and marks the widget; a new source keeps the field only when it reads the same kind of field.
- The field is matched as the site matches it (`_sdListFieldOf()` = `_pg_sw_list_names_field()`, on the arg cleaned as `pg_sw_list_spec()` cleans it).

## What does Bind Data offer inside a list?
- An optgroup "Row of the list" first: the row values of every list the element is drawn in, the nearest list's first, each token once (`_sdListRowOptions()`). The same tokens reach the condition row and the code view completion through `_swBindTokenOptions()`; the position tokens number the list's own rows. `_swBindLabel()` names them.

## What does the canvas show?
- The template once under a cyan band "Values of a field · Colour — one row shown…" (`_sdListBand()`, the table band too). When the site would draw nothing (unknown source, no field, unreadable settings, a kind without lists, outside the record in a form list, a fifth level, a field that gives no rows) the band turns amber with the reason and the list fades. Row values preview as samples (`_SD_LIST_ROW_PREVIEWS`); sample rows follow the card ([[widget-condition-canvas]]). Tree, HTML tree and code view name it a list.

## Which lists must match the server?
- `SW_LIST_SOURCES` (labels, arg, arg required, row tokens, kinds) = `pg_sw_list_sources()` and `pg_sw_kind_lists()`; `_sdListSpec()` = `pg_sw_list_spec()`; `_sdListIsSourced()` = `pg_sw_list_is_sourced()`. `tests/widget_lists_editor_test.php` checks them under Node. No list name holds TOKEN (check_bindings reads `SW_*TOKEN*` as palettes).
- Tests that cut `canDrop()`, `_swBindTokenOptions()` or the comments walk also cut or stub the list helpers.

## What is not done yet?
- Tried by unit tests only: a browser round (palette drag into a form list and a detail view, the panel, the band, Preview) is still owed.
- A source change keeps the row's bindings (no rebinding offer). No condition on a list's own loop_area; a flag naming a row value: [[widget-list-flags]].
