---
name: tables-relational
description: Plan (owner 2026-11-09): workflow tables that behave like SQL tables (keys, row links, joined columns, lists, type changes), flows that pick a row or total rows; steps in tables-relational-steps
---

## Why this plan?
- The owner (2026-11-09) wants workflow tables to behave like SQL tables. A flow should pick a row of a table, or work out values over all of its rows, and use them. Tables that are related should be read together, as a join reads them. He said the work should move the product forward and that development time is not the limit. Skip what adds nothing.
- The trigger was his bakery flow UNNN on dev. "Read a row" took "1" as the row's hidden id, while the table screen numbers rows 1, 2, 3. So it read nothing, and the message box after it failed on an empty message.

## What is there today?
- Store: cells are JSON per row, by column id (`workspace/workflow-tables.md`). The column types are text, number, money, date, person, status, checkbox, a link to a site record and formula. A table holds at most 50 columns and 5000 rows, and a person at most 500 tables. There are no MySQL JSON functions: filtering and sorting happen in PHP.
- Flows already have a row start box, the boxes that add and change a row, and "Read a row" (`workspace-flows-row-get-box.md`).
- Other places: a For each over a list or N times; derived values and Calculate; the tables API and MCP.

## What holds for every step?
- No schema change while the cells stay JSON and the limits hold. A step that needs one asks the owner first (Fixed rules 5).
- Each step ships its whole surface:
  - store check and write;
  - panel screen;
  - flow box and its canvas form;
  - API route with `dry_run`;
  - MCP tool;
  - the Pinegrap AI catalogue hint (`ws_flow_ai_catalogue_hints()`);
  - the import;
  - memory in `docs/memory/workspace/`.
- Values computed over rows (sums, lookups) are worked out when the table is read, like formulas, never stored.
- Money stays in integer minor units, and an average of money is rounded with `round()`.
- Access is unchanged: a flow reads with its owner's rights (`ws_wtable_flow_check()`). A table reached through a link is checked at view for whoever reads it.

## Which decisions are made?
- Owner 2026-11-09: the row box finds its row through a choice. Values over all rows give sums, the row count, averages, and the smallest and largest values.
- The session picked the ways a row is found:
  - its number as the table lists it;
  - its id;
  - the last row.
  - A row found by a column's value comes with step 2.
- A box saved before the choice keeps meaning an id.
- The session put the values over rows in a "Read rows" box. That box takes conditions, so it gives "the sum where …" as well as the sum of all rows. It also gives the rows as a list for For each. "Read a row" stays one row.

## What did the owner decide on 2026-11-09? (`workflow/decisions-made-product-40.md`)
- A deleted row that others link to: the links clear, and the delete confirmation counts the rows affected.
- A link to a table the reader does not read: no value shows, and the joined columns from that table stay empty.
- A type change is refused while a cell would not convert; those cells are listed.
- Keys, both kinds: a stable number per table (1, 2, 3, never shifted by a delete), and a column a person makes the key.

## Where to start?
- Step 1 is PR #8384. Steps 2-8 are in `tables-relational-steps.md`, in order: each needs the one before it, except 7 and 8.
