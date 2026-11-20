---
name: decisions-made-schema-30
description: Decisions list, schema part 30 (2026-11-20 on): the event and its date on stock_notify; the stock plan's tables
---

## Where are the rules and the earlier parts?
- `decisions-made.md`; the generated `DECISIONS.md` lists every part's entries. The part before is schema part 29 (decisions-made-schema-29.md).

## Which columns does an event's waiting list add to `stock_notify`? (architect, 2026-11-20, agent channel message 10143, answer to K1's request 1691)
- Under the owner's yes to two columns and an index on `stock_notify` (poll 91, `decisions-made-schema-25.md`); TK4 of `plans/event-tickets-steps.md` (#10578), in a migration step of the open version.
- `calendar_event_id` INT UNSIGNED NOT NULL DEFAULT 0 and `recurrence_number` INT UNSIGNED NOT NULL DEFAULT 0 (0 for a single event or shared spots, as the event's remaining spots are keyed); KEY `idx_event_sent` (`calendar_event_id`, `recurrence_number`, `sent_at`). No backfill.
- An event's request is a row with `product_id = 0` and the event set; every row before stays a product's request, and the product side's queries do not change.
- A deleted calendar event's waiting rows are deleted with it, so an id handed out again never inherits requests. The export's `stock_notify` section names the event and its date on such a row; erasure runs by address and account as before.

## Which tables does the stock plan add? (architect, 2026-11-20, agent channel message 10139, answer to Arge's request 1686)
- Under the `erp_*` approval in advance (`decisions-made-data.md`) and the owner's yes to the plan (poll 163, message 10064); `erp/erp-stock-planning.md`.
- `erp_stock_days`: `product_id` INT UNSIGNED, `day` DATE, `sold` INT UNSIGNED NOT NULL DEFAULT 0, `stock` INT UNSIGNED NULL (unknown); PRIMARY (`product_id`, `day`), KEY `idx_day`. Written sparse: only a day with a sale or with stock 0; the last 14 days' `sold` counted again each night; returns not subtracted.
- `erp_stock_plan`: one row per product, a cache rebuilt every night; its types in the step's PR body. `erp_accounts.lead_days` SMALLINT UNSIGNED NOT NULL DEFAULT 0.
- The job writes nothing while the Accounting module is off.

Related: docs/memory/workflow/decisions-made.md, docs/memory/workflow/decisions-made-schema-29.md, docs/memory/commerce/back-in-stock-notices.md, docs/memory/workflow/decisions-made-data.md
