---
name: decisions-made-schema-6
description: Decisions list, schema part 6 (2026-10-30 midday on): district and plate_code, iys_sms_status, form_fields.rules, the marketplace auto-invoice switch, erp_quote_links, erp_quote_templates
---

## Where are the rules and the earlier steps?
- `decisions-made-schema.md` (rules, 2026-10-27), `-2` (2026-10-28), `-3` and `-4` (2026-10-29), `-5` (2026-10-29 evening to 2026-10-30 morning). Reasons are on the issues.

## Which schema was approved on 2026-10-30 (midday)?
- Turkish addresses (#3569, architect, one step on the next free general label when pushed): `ship_tos.district VARCHAR(50) NOT NULL DEFAULT ''` and `states.plate_code VARCHAR(4) NOT NULL DEFAULT ''` by `install_add_column()`; the same step fills the plate codes of the 81 Turkish provinces from `pg_sales_map_tr_regions()` by province name (an unmatched row stays empty; a rerun fills only empty ones). The district is free text, optional, shown on checkout and the order screens when the country is Turkey or the chosen method's driver asks for it; the API `ship_tos` fields gain it; the drivers send `district` first and fall back to `city` as today. No district data set (its licence would be a separate decision).
- Marketing SMS (#3570, architect, the next free general label when pushed): `contacts.iys_sms_status VARCHAR(16) NOT NULL DEFAULT ''` by `install_add_column()`, the `iys_email_status` shape (11.0.39): what İYS last said about the number on the `MESAJ` channel.

## Which schema was approved on 2026-10-30 (afternoon)?
- Designed form rules (#3669, architect, the next free general label when pushed): `form_fields.rules TEXT NULL` by `install_add_column()`, a JSON object (`min_date` "today", `required_when` {field, answers}) written by `pg_cf_sync_page_form()` and checked on site submissions in `custom_form.php` only; staff paths (panel, API, MCP) exempt; no data step, the templates set it at install.
- Marketplace auto-invoice switch (#3670, architect, the next free ERP label when pushed): `config.erp_auto_invoice_marketplace TINYINT(1) NOT NULL DEFAULT 0`; at 1 `erp_auto_invoice_decision()` weighs a marketplace order like a web order (`decisions-made-product-9.md`).
- Sales quote guest links (#3671, architect, the next free ERP label when pushed): `erp_quote_links` by `install_create_table()`, in `get_tables()`: `quote_id`, `token_hash CHAR(64)` (unique), `token_hint VARCHAR(8)`, `expires_at`, `used_at`, `revoked_at`, `created_at`, `created_by`; the `short_links` token shape. One live link per issue, a new link revokes the earlier ones, expiry is the quote's `valid_until`.
- Sales quote templates and the follow-up switch (#3672, architect, the next free ERP label when pushed): `erp_quote_templates` (`name VARCHAR(100)`, `scope VARCHAR(16)` personal | site, `user_id`, `form_data MEDIUMTEXT NULL`, `created_at`, `updated_at`; KEY scope, user_id) in `get_tables()`, and `config.erp_quote_expiry_followup TINYINT(1) NOT NULL DEFAULT 0` for the Settings checkbox (the config.php constant still turns it on).

Related: docs/memory/workflow/decisions-made-schema.md, docs/memory/workflow/decisions-made-schema-5.md
