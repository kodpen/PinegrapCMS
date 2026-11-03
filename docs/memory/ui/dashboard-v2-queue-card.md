---
name: dashboard-v2-queue-card
description: The new dashboard's Next up card (queue): its sources and gates, where each count is read (Operations Desk helpers, ERP aging, the drafts folder), the row order, the actions
---

## Where does each row come from?
- `includes/dashboard/v2/cards/queue.php`: `pg_dashboard_v2_queue_counts()` reads one count per source behind its gate; `pg_dashboard_v2_queue_rows()` (pure, `tests/dashboard_v2_queue_test.php`) turns them into rows. Renderer `pgDash.renderers.queue` in `assets/js/pg_dash_cards_work.js`.
- Forms (24 h), comments waiting, critical SEO, accessibility, overdue Workspace tasks, approvals (#4895): the tile figures' reads.
- Shop, behind the `shop` gate (widget 9's own): refunds waiting, orders not shipped (only with shipping on), products out of stock and running low. The counts are widget 9's: `pg_dashboard_ops_refund_summary()`, `pg_dashboard_ops_shipping_summary()` (its 30-day window, `pg_dashboard_ops_shipping_scope()`), `pg_dashboard_ops_stock_counts()` in `includes/dashboard/widgets/widget_9.php`, which the lanes call as well, so the card and the Operations Desk never disagree.
- Accounting, for `ERP_ENABLED` and role < 3 or `manage_erp`: overdue sales invoices from `erp_aging_invoices('sales', today, overdue)`, the source of the ERP dashboard's "Overdue receivables" card.
- Drafts ready, designers only (only they publish): pages in the drafts folder without a publish time still ahead (a scheduled draft goes on the site by itself). One draft is named and opens its editor; several open `view_system_styles.php`.

## In which order?
- Tone first (bad, warn, plain), the source order within a tone; a stable sort written by hand, since `usort()` keeps equal items in order only from PHP 8.0. At most `PG_DASHBOARD_V2_QUEUE_ROWS` (6) rows.
- A refund or an unshipped order is bad once the oldest has waited two days (widget 9's line), warn before.

## Where do the actions lead?
- Refunds: the orders list on `refund_pending`, any type, from the oldest one waiting. Not shipped: the orders list with `advanced_filters=true&shipping_status=unshipped` over the lane's window (the filter is applied only with the advanced filters on). Out of stock: `view_products.php?filter=out_of_stock_products`. Overdue invoices: `erp_invoices.php?filter=overdue&direction=sales`.
