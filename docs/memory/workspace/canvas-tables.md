---
name: canvas-tables
description: The tables bound to a canvas (ws_canvas_tables, 11.0.190, #8960): who binds, read or write, 20 a canvas, the card's line of tables, cleanup, a page's read
---

## What is stored?
- `ws_canvas_tables` (2026.11.0 step 11.0.190, owner answer #8924): `message_id` (the canvas: the message carrying the live page), `table_id`, `access` `read` | `write`, `bound_by_kind` `user` | `app`, `bound_by_id`, `created_at`; `UNIQUE (message_id, table_id)`, `idx_table`. No foreign keys.
- `includes/workspace/canvas_tables.php`; `ws_canvas_tables_ready()` probes `bound_by_kind`. Tests `tests/workspace_canvas_tables_test.php`.

## Who binds a table, and who takes it off?
- A person who reads the canvas, may write in its channel (`ws_can_post_channel()`) and may write the table (`ws_wtable_may(…, 'write')`), for a read binding too: nobody binds a table they could only look at to a page others run. Pure rule `ws_canvas_table_bind_refusal()`: `access_invalid`, `not_found`, `forbidden`, `too_many` (20 a canvas, `WS_CANVAS_MAX_TABLES`; changing a binding's access at the limit passes).
- Binding again changes the access (and the binder); two presses at once write one row (`INSERT IGNORE` on the unique key).
- Taking off: whoever may write in the channel and may write the table, or anyone who may write in the channel when they no longer reach the table, so a binding never outlives everybody's reach.
- An agent's binding (`bound_by_kind` `app`, a channel line with Undo) comes with the API (CO7).

## What does the card show?
- `file.page.tables` [{id, name, access, reached}] and `can_bind` (`ws_canvas_tables_card()`, from `ws_page_card()` with the viewer). The line "Tables it uses" with a chip per table and what the page does ("reads", "reads, writes"); a table the reader does not reach shows as "A table you do not reach", so the line still counts it.
- "Bind a table" for whoever may write in the channel: a drawer of the tables they may change (`ws_page_tables`), each with Read only / Read and write and Remove (`ws_page_bind`, `ws_page_unbind`); the card is drawn again from the message sent back.

## What does a page read?
- Through the bridge (`canvas-bridge.md`): `tables` lists the bound tables the reader reaches with their columns (id, name, type) and the binding's access; `rows` {table (id), where, join, sort, dir, limit, from, totals}.
- `rows` answers `not_bound` for a table the canvas does not bind, `forbidden` when the reader may not read it (`ws_wtable_for(…, 'view')`), `invalid` with the query's words (`ws_wtable_query_refusal()`, `workspace/workflow-tables-query.md`).
- It reads every row (5000 at most), keeps the query's, pages by offset (100 by default, 500 at most), works lookups and rollups out for the reader (`ws_wtable_joined_fill()`) and gives `total`, `next` and, asked, `totals` (`ws_wtable_rows_totals()` over the rows kept).

## What removes bindings?
- A table's final delete (`ws_wtable_delete_final()`) and a thread's purge (`threads.php`). A deleted message's rows stay but reach nothing: the reach check needs a live page.
