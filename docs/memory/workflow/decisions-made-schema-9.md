---
name: decisions-made-schema-9
description: Decisions list, schema part 9 (2026-11-03 on): columns of the external API coverage steps and the designer form fee
---

## Where are the rules and the earlier parts?
- `decisions-made.md`; its Parts line lists the files. Entries name the issue, the date and the migration shape.

## Which columns were approved?
- Returns changed-since (#5927, architect, step X2b of `plans/external-api-coverage.md`): `order_returns.updated_at` INT UNSIGNED NOT NULL DEFAULT 0 with an index on (`updated_at`, `id`), in the next free 2026.11.0 sub-step at landing; backfilled once with GREATEST of the six status stamps; every writer of the row sets it through one helper; `GET /returns?modified_since` pages on it, oldest change first, like `GET /orders`. Reason: `api/api-endpoint-rules.md` allows a since filter only on a stamp every writer moves; a refused refund and a new return label move no stamp.
- Designer form fee (#6129, architect, step FL4 of `plans/designer-form-logic.md`): `custom_form_pages.payment` TEXT NULL (the setting as JSON in the `pg_form_fee_settings_clean()` shape; NULL or '' = no fee, read and written beside the form's other settings), `forms.order_id` INT UNSIGNED NOT NULL DEFAULT 0 with INDEX idx_order_id (order_id), `forms.paid_at` INT UNSIGNED NOT NULL DEFAULT 0 (0 = unpaid; the badge, the Unpaid filter, the system field and the API's read-only `paid` derive from it; set once from `pg_order_complete()`), in the next free 2026.11.0 sub-step at landing; the record points at the order, the order carries nothing, as a subscription points at its renewal order. Reason: "no schema" in #5035 meant no `form_payments` table; `form_data.order_id` means the billing data of an order (read by the order screen, the prefill and the privacy export, deleted with the order), and a widget's `system_region_config` belongs to the placement, not the form.
