---
name: ops-alerts-bell
description: Who sees an operations alert on the bell, the device banner and the API, where its bell row opens, how it names a webhook (pg_ops_alert_visible(), pg_ops_alert_bell_url(), pg_ops_alert_mask_url())
module: ops_alerts
---

## Who sees an alert's bell row?
- Manager and above (role < 3), as System Status. An alert of the `sales` or `shipping` group (sales watch, delivery tower, `pack_short`) only while commerce is on: the pick wave and returns screens refuse then, as the bell's order and stock lines are hidden. The row stays in the table and on System Status (`pg_ops_alert_visible()`, #11358).
- Through the API's notifications only with `orders:read` for an alert of those groups: it names orders, as the `oversold` line does (`pg_ops_alert_about_shop()`, #11358).
- The device banner asks the same rule: `pg_ops_alert()` queues it with the code (`order_total`).

## Which columns does the rule read?
- `order_total` (the code): the bell's badge rows and the ladder columns (`pg_notification_unread_rows()`, `pg_notification_ladder_columns()`) carry it, so the badge counts what the list shows.

## Where does a bell row open?
- The catalogue's `url`, the dashboard for a reader the screen would refuse (`integrity`: `pg_ops_alert_reader_url()`).
- A sales watch row with a `product_id` opens that product while its row is there; once the product was deleted, the code's screen (`pg_ops_alert_bell_url()`, #11358: before, "Page not found").

## How does an alert name a failing webhook?
- Scheme and host only (`pg_ops_alert_mask_url()`): Slack, Discord and Zapier carry the secret in the path; the full URL stays on the webhook's edit screen (#5057).

Tests: `tests/ops_alerts_test.php`, `api_notifications_scope_test.php`, `notification_links_test.php`.
Related: docs/memory/platform/ops-alerts.md
