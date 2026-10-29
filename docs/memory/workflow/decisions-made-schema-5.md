---
name: decisions-made-schema-5
description: Decisions list, schema part 5 (2026-10-29 evening on): subscriptions and subscription_events for recurring billing through the payment drivers; rules in part 1
---

## Where are the rules and the earlier steps?
- `decisions-made-schema.md` (rules, 2026-10-27), `-2` (2026-10-28), `-3` and `-4` (2026-10-29). Reasons are on the issues.

## Which schema was approved on 2026-10-29 (evening)?
- Subscriptions slice 1 (#3183, owner: start now; architect: the tables, the next free general labels when pushed): `subscriptions` by `install_create_table()`, in `get_tables()`: `user_id`, `product_id`, `order_id` (the first order), `order_item_id`, `driver` VARCHAR(32), `remote_id` VARCHAR(100) (empty in store mode), `status` VARCHAR(16) (active, past_due, paused, cancelled, ended), `period` VARCHAR(16), `amount` INT UNSIGNED (minor units), `currency` CHAR(3), `next_charge_at`, `retry_count` TINYINT UNSIGNED, `card_last4` VARCHAR(4), `card_ref` VARCHAR(255) (the PSP token, sealed like other secrets), `created_at`, `updated_at`, `cancelled_at`, `ended_at`; keys (`user_id`), (`status`, `next_charge_at`), (`order_id`). `subscription_events`: `subscription_id`, `kind` VARCHAR(24), `order_id`, `amount` INT UNSIGNED, `remote_event_id` VARCHAR(100) NULL with `UNIQUE KEY uk_subscription_event_remote` (NULL for store-side events, so only webhook ids de-duplicate), `detail` VARCHAR(255), `created_at`; key (`subscription_id`, `id`). A renewal order is marked only through `subscription_events.order_id`: no `orders` column. `email_campaign_profiles.action` gets `subscription_renewed`, `subscription_failed` and `subscription_cancelled` appended in the 2026.11.0 `order_delivered` shape. No `subscription_plans` table: store mode needs none, and a later psp slice keeps remote ids in the driver's settings unless the catalogue argues otherwise. The legacy recurring columns and the PayPal Payments Pro job stay.

Related: docs/memory/workflow/decisions-made-schema.md, docs/memory/workflow/decisions-made-schema-4.md
