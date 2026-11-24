---
name: payment-return-language
description: The address a payment service sends the visitor back to carries the language of the page they paid from (pg_tr_script_url()), so completion, receipt mail and receipt page are in it (#11423)
---

## Which return addresses carry the language?
- The checkout's (`submit_order.php`: `order_preview.php` / `express_order.php?mode=<driver>_return`, and PayPal Express Checkout's `mode=paypal_express_checkout_return`), a form's payment step (`includes/fn/form_payment_step.php`, `custom_form.php`) and a booking deposit (`includes/fn/booking_deposit_pay.php`, `booking_slots.php`): each built through `pg_tr_script_url()`, which adds `pg_lang=<code>` on a page in another language and nothing in the source language.
- PayPal Express Checkout's cancel address and PayTR's ok_url / fail_url are pages: built on `LANGUAGE_PATH` when the request has a language.

## Why?
- The checkout form posts pg_lang, but the return is a new request from the payment service. Without pg_lang the return completed the order, mailed the receipt (`pg_tr_email()`) and redirected to the receipt page in the source language. With it, init.php defines the language and `pg_tr_redirect_hook()` gives the receipt redirect the language's directory.

## What does it not cover?
- A server-to-server notification (webhook) completing the order: no table keeps the order's language, so that receipt is in the source language.

Tests: `tests/multi_language_pages_test.php`.
