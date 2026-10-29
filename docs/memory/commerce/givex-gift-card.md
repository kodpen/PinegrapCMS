---
name: givex-gift-card
description: How Givex gift card replies are read (pg_givex_parse_response), what an unreadable reply does at apply, at the submit check and at redemption, and how to test Givex without the real service.
---

## Where is Givex called?
A code that is no gift card in this system goes to Givex when
`ecommerce_givex` is on: a balance check when the code is applied
(`express_order.php`, `order_preview.php`), again in `submit_order()` before
the order is charged, then a redemption once the order is completed
(`pg_order_complete_gift_cards()`, `order-completion-before-lock.md`).
`send_givex_request()` (`includes/fn/ecommerce.php`) posts XML-RPC to
`https://<primary host>:50042`, the secondary host after a cURL error, through
`pg_curl_run()` (15 s, connect 10 s, fresh connection; `PROXY_ADDRESS`,
`CURL_CA_BUNDLE` and `ALLOW_INSECURE_UPDATE_TLS` apply). A cURL error on both
hosts is answered as `curl_errno` / `curl_error`; any HTTP answer is read by
`pg_givex_parse_response()`. Since #2124 it sends the Pinegrap User-Agent and
refuses http; before, two hand-written cURL blocks had no connect timeout of
their own.

## How is a reply read?
`pg_givex_parse_response($type, $body)` returns either `error_message` or the
values, never neither:
- The reply is a list of strings: transaction code, result (`0` = success),
  then the balance (balance check) or authorization number and new balance
  (redemption). A standard error carries its text third; a non-standard reply
  first.
- Balance check: result `0` with a non-negative number third gives `balance`.
  Anything else (empty body, HTML error page, cut-off list, balance not a
  number or negative, error without text) gives "The gift card service sent
  a reply that could not be read" (tr.json; no trailing period, the callers'
  sentence adds one).
- Redemption with result `0` went through at Givex, so it stays a success:
  `authorization_number` is `''` when missing, `balance` is `null` when
  unreadable, and the redemption then stores `new_balance` as old balance
  minus the amount (the receipt's remaining balance).

Symptom before (#485): unreadable replies warned and showed "no remaining
balance"; a balance that was not a number was a 500 when applying, at the
submit check, and at redemption after Givex had taken the amount (order left
incomplete, a retry redeemed again).

## Which inputs count as not given?
- A code that is empty once dashes and spaces are stripped (`----`) is "could
  not find a gift card for that code"; it is never sent to Givex.
- Order preview `gift_card_code` and `remove_gift_card_from_order.php`
  `applied_gift_card_id` go through `pg_request_single_values()`: missing or
  an array is not given (an array was a 500, #485).

## How to test without Givex?
Sandbox: `config.ecommerce_gift_card = 1`, `ecommerce_givex = 1`, both
hostnames `localhost`; run an HTTPS XML-RPC mock on port 50042 with a
self-signed certificate for `localhost` and define `CURL_CA_BUNDLE` in
`data/config.php` as that certificate. Unit tests:
`tests/givex_gift_card_test.php` (mock replies).
