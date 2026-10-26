---
name: back-in-stock-sms
description: Back-in-stock notice by SMS (PR #1864): kind stock (transactional, owner decision), the stock_back event, the optional number field, text instead of mail, the one-message promise
---

## Is it a marketing message?
- No: owner decision (2026-10-26, #1864, `workflow/decisions-made-messages.md`). The customer asked for this one notice, it goes once, so it is transactional: kind `stock` in `pg_sms_kinds()` (not commercial, Netgsm `iysfilter` 0). The template names the store, the product and its address only; no campaign, discount or other product, or it would need İYS consent.

## When does a request keep a number?
- `pg_stock_notify_sms_on()`: the `stock_back` event of the SMS Messages card is on and `pg_sms_ready()`. Off, the form draws no number field and `pg_stock_notify_request($product_id, $email, $user_id, $phone)` ignores a number sent anyway.
- On, a typed number goes through `pg_stock_notify_phone()` (`pg_sms_number()`: E.164 digits, mobile only) into `stock_notify.phone`; one no text reaches answers status `phone` (after `email`). The e-mail address stays required: the request rules, `already` and the limit are unchanged ([[back-in-stock-notices]]).

## How does the notice go out?
- `pg_stock_notify_send()` claims each row as before; a row with a number, while the event is on, is queued with `pg_sms_message_queue('stock_back', pg_sms_dial($phone), …, 'stock:<row id>')` instead of mailed. Only when the text is not queued (refused, SMS off) does the mail go. One number on two rows gets one text, like one address one mail.
- The stored digits are read back with `pg_sms_dial()` (the plus), so a foreign number never passes for a Turkish one.
- Template fields `^^store^^`, `^^product^^` (`short_description`, else the SKU, one line), `^^url^^` (`pg_stock_notify_url()`, may be empty). Queued with the two-day expiry of every event ([[sms-order-messages]]).

## What does the visitor read?
- `pg_stock_notify_status_message('added', $by_sms)`: "one text message" or "one e-mail". The item view starter has the number field (`stock_notify_phone`) under the address and the line "We will send you one message when it is back in stock." Designs made before keep their tree; the answer sentence says it there.
- The number field binding: `type="tel" name="phone" autocomplete="tel" inputmode="tel" maxlength="50"`, never required, `can_notify` unless flagged ([[catalog-stock-notify-form]]).

## How was it verified?
- `tests/stock_notify_test.php` (number, status, sentences, event, render, wiring) and `tests/stock_notify_item_view_test.php` (the field, its drop, designer). No sandbox run: the paths are the queue and listener already pinned; Netgsm was not reached.
