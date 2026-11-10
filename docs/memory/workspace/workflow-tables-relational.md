---
name: workflow-tables-relational
description: Workflow tables that behave like SQL tables: the owner's aim, the note of each part (keys, links, lookups, rollups, lists, type changes and more), the rules every new part keeps
---

## What is it?
- Owner (2026-11-09, `workflow/decisions-made-product-40.md`): workflow tables behave like SQL tables. A flow picks a row or works out values over the rows, and related tables are read together, as a join reads them. The trigger was a flow whose "Read a row" took "1" as a hidden id and read nothing.

## Which note covers each part?
- Row choice of the row boxes (`by`), PR #8384: `workspace-flows-row-choice.md`.
- Conditions, sort and totals engine, PR #8400: `workflow-tables-conditions.md`; the Read rows box and Read a row by match: `workspace-flows-rows-get-box.md`.
- Screen query bar, totals line, the rows API's `where` and `sort`, PR #8453: `workflow-tables-query.md`.
- Stable row numbers, PR #8461: `workflow-table-row-numbers.md`. The key column, PR #8509: `workflow-table-key-column.md`.
- Row links (a foreign key), PR #8557: `workflow-tables-row-links.md`.
- Lookups, PR #8574: `workflow-tables-lookup.md`. Rollups, PR #8584: `workflow-tables-rollup.md`.
- List columns, PR #8486: `workflow-tables-lists.md`.
- Changing a column's type, PR #8512: `workflow-tables-column-type.md`.

## What does every new part keep?
- A new computed column or a new way of reading rows follows these, as each part above did.
- No schema change while the cells stay JSON and the limits hold (`workflow-tables.md`); a part that needs one asks the owner first (Fixed rules 5).
- Values worked out over rows (formulas, lookups, rollups, totals) are worked out when the table is read, never stored.
- Money stays in integer minor units; an average of money is rounded with `round()`.
- A flow reads and writes with its owner's rights (`ws_wtable_flow_check()`).
- A table reached through a link is checked at view for whoever reads it. Owner (2026-11-09): a link to a table the reader does not read shows no value, and the joined columns from it stay empty.
- A part ships its whole surface:
  - the store check and write;
  - the panel screen;
  - the flow box and its canvas form;
  - the API route with `dry_run`, and so its MCP tool;
  - the Pinegrap AI catalogue hint (`ws_flow_ai_catalogue_hints()`);
  - the import;
  - its memory note in this folder.

## What did the sessions choose?
- Values over rows live in their own "Read rows" box, which takes conditions, so it gives "the sum where …" and the rows as a list for For each; "Read a row" stays one row (`workspace-flows-rows-get-box.md`).
- Joined columns (lookups, rollups) are read once per page and do not feed formulas, conditions, sorts or totals; the reasons are in their notes.

## What comes after?
- `workflow-tables-later.md`.
