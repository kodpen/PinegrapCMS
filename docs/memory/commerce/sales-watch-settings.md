---
name: sales-watch-settings
description: Sales watch settings (SW2, #10772): the sales_watch config_text row, its defaults, sensitivity levels, price drop share, the pause per channel or store, the form part and chat targets
module: sales_watch
---

## Where are the settings kept?
- One `config_text` row `sales_watch`, JSON: `off` (alert codes switched off, so a code added later starts on), `level`, `close_products`, `price_drop` (percent), `paused` (`store`, `site` or `mp_<id>` => end time). No row = the defaults.
- Read through `pg_sales_watch_settings()` (once per request), held to valid values by the pure `pg_sales_watch_settings_normalize()`; written by `pg_sales_watch_settings_encode()`.

## What are the defaults and limits?
- Every alert on (`orders_stopped`, `payment_broken`, `price_error`, `return_spike`) while operations alerts are on; sensitivity `normal`; no product taken off sale; price drop share 50%.
- Levels (`pg_sales_watch_levels()`): low 0.001, normal 0.01, high 0.05. `settings['sensitivity']` is the float; `settings['level']` the name.
- Price drop share held to 10-90% (`settings['price_drop']` is the share, 0.5); read by the price error shield (SW3).

## How does the pause work?
- "Pause the watch until" a date, for the whole store or one channel (the site, each active marketplace account that pulls orders, `pg_sales_watch_channel_list()`). The day is paused through its end: the pause ends at the next midnight of the site's time zone (`pg_sales_watch_pause_end()`).
- `pg_sales_watch_paused($settings, $key, $now)`: the channel's own pause or the store's. In `pg_sales_watch_check()` a paused store reads nothing; a paused channel is not judged (so the other channel alone is named by its own name, not as the store); `payment_broken` needs the site unpaused.
- A past date is dropped on save; a text that is no date refuses the save ("This is not a date.").

## Where is it on the screen?
- Settings › Updates and maintenance › Alerts (`pgset-alerts`), the Sales watch part under the thresholds; hidden with the Operations alerts switch; drawn only with `ECOMMERCE` and the `config_text` table. An alert's checkbox shows once its code is in `pg_ops_alert_codes()`.
- The save writes the row only when the part was drawn (`sales_watch_card`). List fields `sales_watch_alerts`, `sales_watch_pause` are named in the registry's `lists`.
- Chat notifications: each target lists the `group => 'sales'` codes under a "Sales watch" heading; a new target has them all checked, an existing one keeps its own choice.

## How is it tested?
- `tests/sales_watch_settings_test.php`: defaults, stored row, round trip, the pause, the date end, the posted form.

Related: docs/memory/commerce/sales-watch-flow.md, docs/memory/platform/ops-alerts.md
