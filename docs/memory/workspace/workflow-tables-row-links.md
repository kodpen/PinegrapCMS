---
name: workflow-tables-row-links
description: Row links of workflow tables (type row, "Row link", wtable_links.php, PR #8557): a row id of another table, shown by key or first text, checked on write, cleared on delete
---

## What is a row link?
- Column type `row`, "Row link", a foreign key: `options.table` names the table its cells link to, fixed with the type (`type_fixed`). The same table may be named. Its columns show through a lookup (`workflow-tables-lookup.md`).
- A cell keeps the linked row's id, an int (`not_row` otherwise). No schema change.

## What does the store check?
- A new column: the person reads the table it names, else `row_table_invalid` (`ws_wtable_link_column_refusal()`, in `ws_wtable_column_create_check()`).
- A row write (create, update, import, flows): the writer reads the linked table (`row_table_unread`) and the row is there (`row_not_found`), `ws_wtable_link_cells_refusal()`. A link the row already holds is not checked again. A flow writes with its owner's rights.
- A restore from the bin writes the cells as they were: a link to a row gone since reads as empty.

## How does it show?
- `ws_wtable_link_label()`: the linked row's key, else its first filled text cell, else `#N`. `ws_wtable_link_texts()` reads them once per page, checked per linked table for whoever reads (`ws_wtable_link_readable()`).
- Owner (2026-11-09, `workflow/decisions-made-product-40.md`): a reader who does not read the linked table sees "A row of a table you do not read" (`ws_wtable_link_unread_text()`), never the row's text.
- The texts ride in the people list under `WS_WTABLE_LINKS_KEY` (`ws_wtable_people_with_links()`): the list, board cards, Excel download and bin summary show them.
- Row form: a searchable select of the linked table's rows, "text · #N" (`ws_wtable_link_choices()`). A person who does not read the linked table sees a disabled field; it is not posted and the row keeps the link. Query bar: the same list; conditions compare the row id (eq, neq, empty, not_empty).
- Columns card: "Row link · <table name>". New column form: "Linked table", the tables the person reaches, "(this table)" marked.

## What happens when a linked row is deleted?
- Owner (2026-11-09): the links clear and the delete confirmation counts them. `ws_wtable_row_delete()` calls `ws_wtable_links_clear()`: each linking row loses the cell, written on its revision (three tries), its ws_refs tags rewritten; `links_cleared` in the answer. No row event is sent for those rows. A restore of the deleted row does not bring the links back, and the confirmation says so.
- The count: `ws_wtable_link_counts()`, read only when a row column points at the table.
- A deleted table keeps the links to its rows; they read as empty.

## How are the linking rows found?
- `ws_wtable_row_ref_tokens()` gives a link the tag `wtable_row:<row id>`, written to ws_refs with source `wtable_row` (`workspace-ref-sources.md`); `ws_wtable_linking_rows()` reads `idx_ref`. Before ws_refs takes the source nothing is found, and a deleted row's links read as empty instead of being cleared.

## API, MCP, flows, import, privacy
- API and MCP: `options.table`; a cell is the row id, never the linked text; the codes above. The row delete's dry run gives `links_cleared`.
- Flows: a link reads as the row id; Read a row by id on the linked table follows it; Add/Change a row take an id. The AI catalogue hints for `row_get`, `row_set` say so; `row_table_unread` is worded for the flow's owner.
- Import: never guessed or chosen for a new column; into a row column a value is the linked row's text (case aside) or `#N` (`ws_wtable_link_import_rows()`); no match stays text and is refused `not_row`. The download writes the text the screen shows.
- Privacy: a row cell names nobody (`pg_privacy_wtable_cell_hits()`); a deleted column's leftover int is still read as a person id.
- Change type (`wtable_retype.php`) refuses a row link both ways: `type_not_changeable` from it, `type_unknown` to it.

## Tests
- `tests/workspace_wtable_links_test.php`, `tests/workspace_flow_row_links_test.php`.
