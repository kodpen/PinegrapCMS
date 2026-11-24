---
name: bug-hunt-areas-ops-alerts-once
description: The bug hunt of staff alerts told once (2026-11-24, #11358): pg_ops_alert() and the bell lines on it; the five faults fixed, what was read clean, what was not there yet
module: ops_alerts
---

## Which area was hunted?
- Architect (2026-11-24, area 10): `pg_ops_alert()` and the bell lines on it: contacts_hot, the volunteer document dates, the walk-in queue, the pack and stock lines; once a day, two runs at once, who gets it, the bell link, a deleted subject, the mail and chat copies.
- Read: `includes/fn/ops_alerts.php` (catalogue, claim, state file, sender), `includes/notifications.php` (visibility, display, badge columns), `lead_tell.php`, `pack.php` (`pg_pack_after_short()`), `stock_sales.php` (`oversold`), `delivery_tower_tell.php`, `notify_channels.php` (`pg_notify_ops_alert()`, `pg_notify_enqueue()`), `push.php` (enqueue, still relevant), `create_notification()`.

## What did the hunt's PR fix?
- A sales, delivery tower or pack-short alert stayed on the bell with commerce off and opened a screen that refused (`pg_ops_alert_visible()`).
- Through the API a key without `orders:read` saw those alerts, which name orders (`pg_ops_alert_about_shop()`).
- A sales watch row of a deleted product opened "Page not found" (`pg_ops_alert_bell_url()`).
- With `data/temp` unwritable an alert with a subject was sent on every pass, ten-minutely mail (`pg_ops_alert_reads_bell()`).
- Hot contacts: the fixed last-day window left out contacts that turned hot between a send and the next pass (`pg_lead_alert_since()`).

## What was read clean?
- The claim under `flock()` before any send: two runs at the same moment send once; mail, banner and chat copies are queued once per claimed send.
- `oversold`: one line per order, hidden with commerce off and without `orders:read`.

## What was judged decided, not a fault?
- `pack_short` and the delivery tower are once a day per code: a second short pack that day is in the activity log and the order's audit, not on the bell (catalogue comment "Once a day").
- Alerts reach manager and above only; a Packing-only user is not told (System Status rule).

## What was not there yet?
- The volunteer document dates and the walk-in queue raise no alert or bell line on `development` (2026-11-24): nothing to hunt.
