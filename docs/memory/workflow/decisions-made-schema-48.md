---
name: decisions-made-schema-48
description: Decisions list, schema part 48 (2026-11-22 on): erp_order_costs.source gains the value supplier for a supplier feed's cost, under the ERP schema approval; how an ENUM value is appended
---

## Where are the rules and the earlier parts?
- `decisions-made.md`; the generated `DECISIONS.md` lists every part's entries. The part before: `decisions-made-schema-47.md`.

## Does erp_order_costs.source gain "supplier"? (architect, 2026-11-22, agent channel message 11988, #11121)
- Yes: the ENUM gets `supplier` appended after its eight values (`invoice`, `average`, `bundle`, `gateway`, `marketplace`, `label`, `rate`, `estimate`). Nothing is dropped; NOT NULL and the default `rate` stay.
- No owner question: `erp_order_costs` is an ERP table, and adding what an ERP step needs is approved in advance (owner, 2026-10-26, #1773, `decisions-made-data.md`). The supplier feed plan's F11 already names the source.
- Why: a line with no average cost takes the feed's buy price (`plans/supplier-feed-features.md`, F11). Stored as `estimate`, the order screen could not tell a feed's cost from a marketplace estimate.
- Not chosen: source `estimate` with `source_ref` `item:<id>` and no schema change.
- An order with a feed cost still reads `estimate` as a whole; only the row's source differs.

## How is a value appended to an ENUM of the open version? (architect, 2026-11-22)
- A new idempotent step on the next free label: it reads the column's type and runs the MODIFY only while the value is missing, with the full list, the value last, and the column's NULL rule and default repeated.
- The CREATE TABLE of the step that made the table (here 11.0.250) gets the value too, so a fresh install and an upgraded site end with the same column.
- Syntax that MySQL 5.7 and MariaDB 10.2 run; the step is rerun twice on a dev database and named as the PR's `[SCHEME]` item.
