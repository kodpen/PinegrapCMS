---
name: decisions-made-schema-8
description: Decisions list, schema part 8 (2026-11-02 on): tables and columns of the 2026-11 feature plans (#5032-#5042) and the Assistant card summary columns (#5014)
---

## Where are the rules and the earlier parts?
- `decisions-made.md`; its Parts line lists the files. Entries name the issue, the date and the migration shape.

## The Assistant card's daily summary (architect, 2026-11-02, #5014)
- `config.ws_claude_summary_hour` TINYINT UNSIGNED DEFAULT 255 (off), `ws_claude_summary_channel_id` and `ws_claude_summary_message_id` INT UNSIGNED DEFAULT 0; the summary is Claude's message in the chosen channel, read by id. Off on every site until switched on.

## The 2026-11 feature plans (architect, 2026-11-02; shapes in the issues)
- Additive steps in the open version with the idempotent helpers; an ENUM is widened by appending through `install_column_info()` and `install_modify_column()`; no DROP, no narrowing MODIFY; each plan's first schema step takes the next free sub-step label when it lands.
- Bookings (#5032): `booking_services`, `booking_staff`, `bookings` (unique staff + start, `hold_until`); four `booking_*` campaign actions.
- Audiences (#5033): `contact_groups.kind`, `rules`, `computed_at`, `compute_cursor`; `users_contact_groups_xref.source`.
- Importers (#5034): `imports`, `import_items`; no column on `forms`, `products`, `contacts`, `comments` or `files`.
- Form fees (#5035): no `form_payments` table; a fee is a one-line order of a product.
- Mail bounces (#5036): `mail_bounces`, one row per address with its current state.
- Object storage (#5037): `files.storage` ENUM(local, s3), `files.storage_key`.
- Experiments (#5038): `experiments`, `experiment_variants`, rollup `experiment_hourly`.
- Store credit (#5039): `gift_cards.user_id`, `kind`, `reason`, `issued_by`, `expires_at`; `credit_issued`, `credit_expiring` actions.
- Web vitals (#5040): rollups `page_vitals_hourly`, `ai_fetch_daily`, 400 days.
- ActivityPub (#5041): `ap_followers`, `ap_outbox`, `ap_blocks`, `comments.remote_actor`, `remote_id`, settings and sealed keys in `config.ap_*`.
- Stock locations (#5042): `stock_locations`, `product_stock`, `stock_moves`, `shipping_methods.stock_location_id`, `orders.stock_location_id`; no data move in the migration, the first location seeds the rows.
