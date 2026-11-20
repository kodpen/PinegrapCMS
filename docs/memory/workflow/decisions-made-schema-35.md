---
name: decisions-made-schema-35
description: Decisions list, schema part 35 (2026-11-20 on): the ad_conversion_events table of plan ad-conversions, the send queue, the event log and the buyer's ids until sent
---

## Where are the rules and the earlier parts?
- `decisions-made.md`; the generated `DECISIONS.md` lists every part's entries. The part before: `decisions-made-schema-34.md`.

## Does the ad conversions plan get a table? (owner, 2026-11-20, agent channel poll 174, channel message 10512)
- "Evet, tek tablo eklensin": one new table, `ad_conversion_events`, which is the send queue, the panel's event log and the place of the buyer's ids until the event is sent.
- Not chosen: no table (the webhook queue with the ids in a held row, the log in a settings row), a column on `orders`.

## Which columns does it have? (architect, 2026-11-20, channel message 10514)
- One row per event and service. `id` BIGINT UNSIGNED AUTO_INCREMENT; `service` VARCHAR(20); `event` VARCHAR(30); `order_id` INT UNSIGNED, 0 without an order; `event_id` VARCHAR(64).
- `ids` TEXT NULL (that service's browser and click ids, the address and the browser's name; NULL once sent or given up); `detail` TEXT NULL (what the event says that is not about a person).
- `person` TINYINT; `status` TINYINT (0 waits for the order, 1 due, 2 sent, 3 refused or given up); `attempts` TINYINT UNSIGNED; `next_attempt_at` INT UNSIGNED; `last_status_code` SMALLINT UNSIGNED; `last_error` VARCHAR(500); `created_at`, `sent_at` INT UNSIGNED.
- Keys: unique (`service`, `event_id`); (`status`, `next_attempt_at`); `order_id`; (`service`, `created_at`).
- Rows older than 30 days are swept, whatever their status. No plain e-mail or phone is ever in a row: the body is built at send time.
- `api_webhook_queue` carries none of these events: its dispatcher sends one row at a time within 20 seconds a pass, so a slow ad service would hold back order webhooks and chat messages.
- The worker of the plan's first step writes the columns into its PR body and asks the architect before it is marked ready.

Related: docs/memory/plans/ad-conversions.md, docs/memory/workflow/decisions-made-product-87.md
