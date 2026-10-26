---
name: marketplace-commission-and-invoice-columns
description: marketplace_product_map.commission and marketplace_order_map.invoice_sent (2026.11.0, 11.0.37): what each holds, NULL vs 0, the probes code reads them through (#1687)
---

## What do the two columns hold?
- `marketplace_product_map.commission` DECIMAL(6,2) NULL: the commission rate in percent the marketplace reports for an approved listing (Trendyol answers it with the approved product). NULL until a rate is read; 0.00 would say the marketplace takes nothing. Shown beside the listing, never used in a price.
- `marketplace_order_map.invoice_sent` INT UNSIGNED, default 0: the unix time the marketplace accepted the invoice link of an imported package; 0 while it has not.

## Where are they added?
- `upgrade_2026_11_0_marketplace_columns()` in `includes/migrations/2026.11.0.php`, label 11.0.37, with `install_add_column()`; test `tests/marketplace_columns_test.php`. CI's install check runs it twice (an empty database, then over the first run's tables) on six servers.

## How does code read them?
- Only after asking `mp_commission_ready()` or `mp_invoice_sent_ready()` (`includes/api/outbound/connectors/base.php`, loaded by every outbound file): exact-name probes through `pg_schema_has()`. The files land before the upgrade step runs (`release/upgrade-bridge-rule.md`), so a statement naming a column the database lacks fails and returns `false`.

## Who writes them?
- `commission`: the approval read of Trendyol listings, at the read that sets `remote_id` (`trendyol-listing-update.md`).
- `invoice_sent`: the "Send invoice link" form of an imported order (`trendyol-invoice-link.md`), only after a 2xx, with `AND invoice_sent = 0`.

Related: docs/memory/plans/marketplace-trendyol-hepsiburada.md, docs/memory/commerce/trendyol-listing-status.md, docs/memory/commerce/trendyol-orders.md
