---
name: decisions-made-schema-31
description: Decisions list, schema part 31 (2026-11-20 on): the order profit tables erp_order_costs, erp_order_profit and erp_cost_rates
---

## Where are the rules and the earlier parts?
- `decisions-made.md`; the generated `DECISIONS.md` lists every part's entries. The part before: `decisions-made-schema-30.md`.

## Which tables does an order's profit add? (architect, 2026-11-20, agent channel message 10292, answer to K2's request 1738)
- Under the same approval and the owner's yes to the plan (poll 164, message 10092); NP1 of `plans/order-profit-steps.md` (#10626).
- `erp_order_costs`: `id` BIGINT UNSIGNED, `order_id` INT UNSIGNED, `kind` ENUM(product, payment, marketplace, shipping, other), `source` ENUM(invoice, average, bundle, gateway, marketplace, label, rate, estimate), `source_ref` VARCHAR(100), `amount` BIGINT, `created_at`, `updated_at`; UNIQUE (`order_id`, `kind`, `source_ref`), so a rerun adds no row.
- `erp_order_profit`: PRIMARY `order_id`; `order_date`, `channel` VARCHAR(48), `revenue`, five `cost_*` columns and `net` BIGINT, `margin` DECIMAL(10,1) NULL, `state` ENUM(final, estimate, incomplete), `basis` CHAR(32) ascii, `stale`, `computed_at`; KEY `idx_stale`, `idx_date`, `idx_channel_date` (`channel`, `order_date`), `idx_net`.
- `erp_cost_rates`: `kind` ENUM(payment, shipping, marketplace), `rate_key` VARCHAR(100), `percent` DECIMAL(6,3), `fixed` BIGINT, `updated_by`, `updated_at`; UNIQUE (`kind`, `rate_key`).
- Amounts are minor units in the currency of the order's own figures; a fee read in another currency is converted first, through `includes/erp/money.php`. `margin` is always net × 100 / revenue, rounded, NULL without revenue.
- A deleted order loses its rows in the two order tables; `erp_cost_rates` holds no order rows and a rate whose method or account is gone may stay. No personal data; not in the erasure list.
- It reads a service's deductions to cost an order only: no receipt, till, bank or account move, no payout record (message 10189, `erp/erp-not-built-on-purpose.md`). Profit is shown behind `validate_erp_access()` on every surface.

Related: docs/memory/workflow/decisions-made.md, docs/memory/workflow/decisions-made-schema-30.md, docs/memory/workflow/decisions-made-data.md, docs/memory/erp/erp-not-built-on-purpose.md
