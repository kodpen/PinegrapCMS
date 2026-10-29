---
name: payment-driver-paytr
description: PayTR iFrame driver (method 'PayTR', 11.0.122, PR #3036): get-token hash, framed payment page, server-to-server callback with the OK answer completes the incomplete order; CSP for the frame; limits
---

## What is it?
- `includes/payments/paytr.php`, key `paytr`, redirect driver for the payment method `PayTR` (appended to the `orders.payment_method` ENUM by 11.0.122, `upgrade_2026_11_0_paytr()`).
- `connect_apps` `payment_paytr`: `merchant_id`, `merchant_key` and `merchant_salt` (sealed), `mode` (`live`/`test` → PayTR's `test_mode`). Switch and fields on the Ecommerce card's redirect rows. No `complete()`, so no return mode.
- No SDK: `pg_curl_run()` POST to `https://www.paytr.com/odeme/api/get-token`; `pg_payment_paytr_authorize_with()` / `_notify_with()` take settings and the HTTP caller for tests.

## How does a payment go?
- `authorize()`: `merchant_oid` = `PG<order id>T<unix time>` (letters and digits, new per attempt); `payment_amount` = hundredths (kuruş) with `round()`; basket = base64 JSON `[name, "unit price", qty]`; `no_installment=1`, `max_installment=0` (the amount taken is the total); currency TRY → `TL` (EUR, USD, GBP, RUB as is; another store currency is refused). `paytr_token` = base64 HMAC-SHA256 (key) of merchant_id, user_ip, merchant_oid, email, payment_amount, user_basket, no_installment, max_installment, currency, test_mode + salt (`pg_payment_paytr_token_hash()`).
- Answer: `redirect` with `html` = a small page framing `https://www.paytr.com/odeme/guvenli/<token>` plus PayTR's `iframeResizer.min.js`; `transaction_id` = the merchant_oid, so the incomplete order keeps it (`pg_payment_started_order_values()`, A1).
- `merchant_ok_url` = the checkout page's next page (`pg_order_checkout_page_row()`), else the checkout page; `merchant_fail_url` = the checkout page. Neither confirms anything.

## How is the callback read?
- `payment_callback.php?driver=paytr` ("Webhook Address" on the settings; the operator enters it as the Notification URL in the PayTR panel). Body form-encoded; hash = base64 HMAC-SHA256 (key) of merchant_oid + salt + status + total_amount (`pg_payment_paytr_callback_hash()`), compared with `hash_equals()`.
- No key/salt, fields missing or a wrong hash → failure, 400 JSON, no `OK` (PayTR sends it again). `status=success` → `paid`, `amount` = `total_amount`, `raw['currency']` from PayTR's `currency` (`TL` → `TRY`), `reply` `OK`. `failed`, or an oid not this driver's shape → ignored, 200 `OK`.
- `pg_payment_callback_reply()` (callback.php): a driver's `reply` is sent as `text/plain` for every 200 outcome (completed, nothing, unknown_order, alerted, ignored); 400/500 keep the JSON answer.
- `complete` outcome → `pg_order_complete_from_payment()` (`order-completion-from-payment.md`), as Stripe and PayPal.

## CSP?
- The definition's `csp`: `script-src` and `frame-src` `https://www.paytr.com`. `pg_payment_csp_sources($gateway, $card_on, $store_on)` adds every redirect driver with `csp` whose `connect_apps` switch is on (`pg_payment_redirect_switched_on()`, one `enabled` query: the policy is sent before the site's key is read); `pg_payment_csp_merge()` joins the lists. waf.php passes `$store_on` = `config.ecommerce`.

## Limits?
- No instalments, refund or cancel through PayTR yet (plan 4c2): refund on PayTR's panel.
- The visitor who pays reaches the receipt page while the order is completed by the callback: the receipt does not show the order (no session `completed_order_id`); if the callback is late the cart still shows it.
- A success for an earlier attempt's merchant_oid (another tab) is `unknown_order`: nothing done, no alert.
- A test-mode payment completes the order like a live one; the log line says "(test mode)".

## How was it verified?
- `tests/payment_paytr_test.php` (fixed hash vectors, stand-in get-token answers, outcome and reply). No live or test PayTR call, no browser round trip.

Related: commerce/payment-drivers.md, commerce/payment-callback.md, commerce/payment-method-lists.md, plans/payment-driver-layer.md
