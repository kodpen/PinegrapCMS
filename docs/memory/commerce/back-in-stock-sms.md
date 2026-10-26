---
name: back-in-stock-sms
description: Back-in-stock notice by SMS (PR #1864): kind stock (transactional, owner decision), the stock_back event, the optional number field, text instead of mail, the one-message promise
---

## Is it a marketing message?
- No: owner decision (2026-10-26, #1864, `workflow/decisions-made-messages.md`). The customer asked for this one notice, it goes once, so it is transactional: kind `stock` in `pg_sms_kinds()` (not commercial, Netgsm `iysfilter` 0). The template names the store, the product and its address only; no campaign, discount or other product, or it would need İYS consent.

## When does a request keep a number?
- `pg_stock_notify_sms_on()`: the `stock_back` event of the SMS Messages card is on and `pg_sms_ready()`. Off, the form draws no number field and `pg_stock_notify_request($product_id, $email, $user_id, $phone)` ignores a number sent anyway.
- On, a typed number is kept in `stock_notify.phone` only when the provider can text it: `pg_stock_notify_phone()` = `pg_sms_number()` (E.164 digits) and `pg_sms_reachable($digits, $provider)` (the driver's `pg_sms_<key>_number()`; Netgsm: Turkish mobiles only; no provider: none). Any other answers status `phone` (after `email`); the visitor corrects it or leaves it empty and gets the mail. Symptom fixed (#1864): `+49 151 …` was kept, its text replaced the mail, Netgsm refused it when sent, nothing came.
- The number is held like the address, whatever address comes with it (#1864): `already` when it waits for the product, `limit` at `PG_STOCK_NOTIFY_PENDING_LIMIT` (50) products; `pg_stock_notify_request_status()` takes it as `$number` (reachable, already, waiting). Why: one number typed beside a fresh address each time could wait on every product, a paid text each. The e-mail address stays required ([[back-in-stock-notices]]).

## How does the notice go out?
- `pg_stock_notify_send()` claims each row as before; a row with a number, while the event is on and the general job sends the SMS queue (`pg_sms_job_alive()`), is queued with `pg_sms_message_queue('stock_back', pg_sms_dial($phone), …, 'stock:<row id>')` instead of mailed. The mail goes when the job is not running (the text would wait and expire), the provider no longer texts the number, or the text is not queued (refused, SMS off). One number on two rows gets one text, like one address one mail.
- The stored digits are read back with `pg_sms_dial()` (the plus), so a foreign number never passes for a Turkish one.
- Template fields `^^store^^`, `^^product^^` (`short_description`, else the SKU, one line), `^^url^^` (`pg_stock_notify_url()`, may be empty). Queued with the two-day expiry of every event ([[sms-order-messages]]).

## What does the visitor read?
- `pg_stock_notify_status_message('added', $by_sms)`: "one text message" or "one e-mail". The item view starter has the number field (`stock_notify_phone`) under the address and the line "We will send you one message when it is back in stock." Designs made before keep their tree; the answer sentence says it there.
- The number field binding: `type="tel" name="phone" autocomplete="tel" inputmode="tel" maxlength="50"`, never required, `can_notify` unless flagged ([[catalog-stock-notify-form]]).

## How was it verified?
- `tests/stock_notify_test.php` (number and provider, status with the number's limits, sentences, event, render, wiring), `tests/sms_test.php` (`pg_sms_reachable()`) and `tests/stock_notify_item_view_test.php` (the field, its drop, designer). No sandbox run: the paths are the queue and listener already pinned; Netgsm was not reached.
