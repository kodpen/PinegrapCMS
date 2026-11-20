---
name: back-in-stock-withdraw
description: Taking a back-in-stock or waiting list request back (#10713): withdraw=1 on stock_notify.php, which row goes, one answer with or without a row, the "Stop waiting" button
module: stock_notify
---

## Can a visitor take a request back?
- Yes (#10713). Architect (2026-11-20, agent channel message 10558): withdrawing is added; no link in a mail, no confirmation mail, no token column, no schema change.
- Why no link: the notice mail is sent once and its row is already closed then; a confirmation mail would let anybody make the site mail any address. A request is kept without proof of the address, so taking it back asks for no more; the harm of abuse is one notice not sent.

## How is it asked?
- `stock_notify.php`, the same POST with `withdraw=1`: the same checks in the same order (body size, method, token, `pg_stock_notify_throttled()`), then `pg_stock_notify_withdraw()`.
- A product: `product_id`. An event's date: `product_id` 0, `calendar_event_id`, `recurrence_number` (the date is worked out as for a request, `pg_stock_notify_event_date()`). A request for an event still goes through `reserve_calendar_event.php`.

## Which row goes?
- `pg_stock_notify_withdraw_status()` (pure) picks the ids: signed in, the account's waiting rows of that product or date, whatever address was typed; a guest, the rows of the address typed (any account's). Only `sent_at = 0`; a sent row, another product, another date stay.
- The delete repeats `AND (sent_at = 0)`, so a row the sender claimed meanwhile stays; the row's device waits (`site_push_watch`, kind 2) go first.

## What is answered?
- `withdrawn`, with or without a row: "You will get no message about this product." / "... about this date." (`pg_stock_notify_withdraw_message()`), so the answer tells nothing new. A guest with no valid address: `email`; no table: `unavailable`.
- JSON: `{status, ok, message}`. A form goes back to `send_to`, the sentence in `catalog_detail` (product) or `reserve_calendar_event` (event).

## Where is "Stop waiting" offered?
- After `added` or `already` only. XHR: the JSON carries `withdraw: {label, name: "withdraw", value: "1"}`; the script posts the same request again with it.
- Form path: the notice carries a button `form="pg-stock-notify-stop"` (`pg_stock_notify_withdraw_button_html()`), since a notice may sit inside the add-to-cart form. The request is kept in the session (`pg_stock_notify_withdraw_offer()`, key `stock_notify_stop`, a personal session: the page is not stored in the cache).
- The hidden form (`pg_stock_notify_withdraw_form_html()`, once per page by `pg_stock_notify_withdraw_form_once()`) is printed by the item view beside its back-in-stock form and by `pg_stock_notify_event_line()` for that event. A visitor who changes their mind later types the address again and presses the button of the `already` answer.
- The designer palette is not touched.

Test: `tests/stock_notify_test.php` (`test_stock_notify_withdraw_*`).
