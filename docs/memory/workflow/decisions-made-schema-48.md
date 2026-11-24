---
name: decisions-made-schema-48
description: Decisions list, schema part 48 (2026-11-22 on): erp_order_costs.source gains supplier; ws_notes.owner_channel_id for a channel's note; the ws_share_links table
---

## Where are the rules and the earlier parts?
- `decisions-made.md`; the generated `DECISIONS.md` lists every part's entries. The part before: `decisions-made-schema-47.md`.

## Does erp_order_costs.source gain "supplier"? (architect, 2026-11-22, agent channel message 11988, #11121)
- Yes: the ENUM gets `supplier` appended after its eight values (`invoice`, `average`, `bundle`, `gateway`, `marketplace`, `label`, `rate`, `estimate`). Nothing is dropped; NOT NULL and the default `rate` stay.
- No owner question: `erp_order_costs` is an ERP table, and adding what an ERP step needs is approved in advance (owner, 2026-10-26, #1773, `decisions-made-data.md`). The supplier feed plan's F11 already names the source.
- Why: a line with no average cost takes the feed's buy price (`commerce/supplier-feed-cost-api.md`, F11). Stored as `estimate`, the order screen could not tell a feed's cost from a marketplace estimate.
- Not chosen: source `estimate` with `source_ref` `item:<id>` and no schema change.
- An order with a feed cost still reads `estimate` as a whole; only the row's source differs.

## How is a value appended to an ENUM of the open version? (architect, 2026-11-22)
- A new idempotent step on the next free label: it reads the column's type and runs the MODIFY only while the value is missing, with the full list, the value last, and the column's NULL rule and default repeated.
- The CREATE TABLE of the step that made the table (here 11.0.250) gets the value too, so a fresh install and an upgraded site end with the same column.
- Syntax that MySQL 5.7 and MariaDB 10.2 run; the step is rerun twice on a dev database and named as the PR's `[SCHEME]` item.

## Does ws_notes get a column for the channel that owns a note? (owner, 2026-11-23, in the architect's window)
- Yes: `ws_notes.owner_channel_id` INT UNSIGNED NOT NULL DEFAULT 0 with an index. 0 is a person's note; a value is the channel the note belongs to. `user_id` keeps who wrote it.
- Not chosen: a table of its own for channel notes; versions, editing, export and the API would be written a second time.
- Built by PR #11198 (`workspace/channel-note-owned.md`).

## Is there a table for share links? (owner, 2026-11-23, in the architect's window)
- Yes, `ws_share_links`: `id`, `token_hash` CHAR(64) unique, `channel_id`, `message_id`, `kind`, `item_id`, `created_by_kind` / `created_by_id` (`user` or `app`), `created_at`, `expires_at`, `revoked_at`, `opened_at`, `opens`; keys on `channel_id` and on (`kind`, `item_id`).
- The token is 43 random characters kept only as its SHA-256, as `ws_approval_guests.token_hash`: the address is shown once and a lost one is made again.
- Not chosen: the token kept readable so "Copy link" works later; whoever reads the database would read every link.
- Step SL1 of `plans/channel-share-links.md`.
