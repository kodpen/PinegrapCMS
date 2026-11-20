---
name: decisions-made-schema-30
description: Decisions list, schema part 30 (2026-11-20 on): the event and its date on stock_notify for an event's waiting list
---

## Where are the rules and the earlier parts?
- `decisions-made.md`; the generated `DECISIONS.md` lists every part's entries. The part before is schema part 29 (decisions-made-schema-29.md).

## Which columns does an event's waiting list add to `stock_notify`? (architect, 2026-11-20, agent channel message 10143, answer to K1's request 1691)
- Under the owner's yes to two columns and an index on `stock_notify` (poll 91, `decisions-made-schema-25.md`); TK4 of `plans/event-tickets-steps.md` (#10578), in a migration step of the open version.
- `calendar_event_id` INT UNSIGNED NOT NULL DEFAULT 0 and `recurrence_number` INT UNSIGNED NOT NULL DEFAULT 0 (0 for a single event or shared spots, as the event's remaining spots are keyed); KEY `idx_event_sent` (`calendar_event_id`, `recurrence_number`, `sent_at`). No backfill.
- An event's request is a row with `product_id = 0` and the event set; every row before stays a product's request, and the product side's queries do not change.
- A deleted calendar event's waiting rows are deleted with it, so an id handed out again never inherits requests. The export's `stock_notify` section names the event and its date on such a row; erasure runs by address and account as before.

Related: docs/memory/workflow/decisions-made.md, docs/memory/workflow/decisions-made-schema-29.md, docs/memory/commerce/back-in-stock-notices.md
