---
name: decisions-made-schema-8
description: Decisions list, schema part 8 (2026-11-02 on): tables and columns of the 2026-11 feature plans (#5032-#5042) and the Assistant card summary columns (#5014, #5227)
---

## Where are the rules and the earlier parts?
- `decisions-made.md`; its Parts line lists the files. Entries name the issue, the date and the migration shape.

## The Assistant card's daily summary (architect, 2026-11-02, #5014, #5227)
- `config.ws_claude_summary_hour` TINYINT UNSIGNED DEFAULT 255 (off) and `ws_claude_summary_channel_id` INT UNSIGNED DEFAULT 0 (11.0.145); the summary is Claude's message in the chosen channel, and the card reads the latest answered summary request's `ws_ai_requests.reply_message_id`. The third column #5014 planned (`ws_claude_summary_message_id`) broke the row room `tests/innodb_test.php` keeps, so it was dropped (#5227). Off on every site until switched on. The `config` row is full from here (`data/config-table-no-varchar.md`).

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
- Small features (#5047): `site_search_daily` rollup, `config.search_synonyms` and `search_promoted` TEXT JSON; `comments.kind`, `answer_of`; `products.preorder`, `preorder_date`.
- EU VAT on the order (#5559): `orders.vat_id` VARCHAR(20) NOT NULL DEFAULT '', `vat_checked_at` INT UNSIGNED NOT NULL DEFAULT 0, `vat_request_id` VARCHAR(32) NOT NULL DEFAULT '' (the VIES consultation number), in the next free 2026.11.0 sub-step at landing; the 2026-10-20 "no tax column on orders" rule narrows for these three only, the VKN/TCKN still comes from the ERP account. Written once when the order is written, read by the order screen, the print view, the API `orders` resource and the ERP invoice; never rewritten from the contact card.
