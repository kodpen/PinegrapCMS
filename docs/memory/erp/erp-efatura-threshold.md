---
name: erp-efatura-threshold
description: The e-Fatura threshold warning (includes/erp/efatura_threshold.php): the two threshold settings, what counts as a sale, the daily cached sum, the System Status tile and its deadline.
---

## What does it warn about?
- A seller in Turkey outside e-Fatura must join it from 1 July of the year after its gross sales reached a threshold: 500 Bin TL for sales over the internet, 3 Milyon TL for all sales (2026 amounts). The store keeps both as settings so a new year's amounts need no release (PR #1773). What counts and the daily cache are owner decisions (`workflow/decisions-made.md`).

## Where are the thresholds kept?
- `config.erp_efatura_web_threshold` and `erp_efatura_general_threshold` (11.0.61, BIGINT UNSIGNED, minor units, defaults 50000000 / 300000000); constants `ERP_EFATURA_WEB_THRESHOLD` / `ERP_EFATURA_GENERAL_THRESHOLD` (0 before the step). 0 switches that warning off; a site already on e-Fatura sets both to 0.
- ERP Settings, card "e-Fatura threshold" (`erp_settings.php#erp_efatura_threshold`), only with `erp_turkish_features()`; typed in whole units, digits only (a thousands dot would turn 500.000 into 500), saved by `erp_efatura_threshold_save()`, which drops the System Status cache.

## What counts as a sale?
- `erp_efatura_threshold_sales($year)`: orders with `erp_order_billable_statuses()` and `order_date` in the calendar year (site time zone), `total + gift_card_discount` (the whole sale, VAT included, base currency). Internet: every type but `local` (marketplace included). All: counter sales too. ERP invoices without an order are not counted.
- `gift_card_discount` is UNSIGNED: it is CAST to SIGNED, or a negative total makes the sum an out-of-range error under strict SQL.

## How is it weighed?
- `erp_efatura_threshold_rows()` (pure, `tests/erp_efatura_threshold_test.php`): reached at the threshold itself, near from 80 % (`ERP_EFATURA_THRESHOLD_NEAR`), share cut down to whole percent, deadline `Y+1-07-01`. `erp_efatura_threshold_worst()` picks the tile's state.

## Where is it shown, and how often is it read?
- System Status tile "e-Fatura Threshold" (actions column, not scored), only with the ERP on, `erp_turkish_features()` and a threshold near or reached: yellow "Near", red "From <deadline>", one detail row per threshold.
- No scheduled job: `erp_efatura_threshold_report()` keeps the sums in `data/temp/erp_efatura_threshold.json` for a day (and per year), so the orders are read at most once a day, when the status is built. A failed read is not cached.

Related: docs/memory/erp/erp-auto-invoice.md
