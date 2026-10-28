---
name: payment-gateway-secrets
description: Legacy gateway secrets (Authorize.net key, PayPal passwords, iyzipay secret) sealed in config, drawn masked, opened in init.php; plain values still read; the 47-character limit
module: payments
---

## Which secrets, and how are they stored?
- `pg_gateway_secret_fields()` (`includes/fn/payments.php`): `ecommerce_authorizenet_transaction_key`, `ecommerce_paypal_payflow_pro_password`, `ecommerce_paypal_payments_pro_api_password`, `ecommerce_paypal_express_checkout_api_password` (VARCHAR 100) and `ecommerce_iyzipay_secret_key` (VARCHAR 255).
- Each column holds `pgenc1:<base64 ciphertext>:<base64 iv>` (AES-256-CBC, key = sha256 of `ENCRYPTION_KEY`; `pg_gateway_secret_seal()` / `pg_gateway_secret_open()`). The prefix tells it from a plain value, which may contain `:`.
- Before PR #NNNN they were plain and printed into `value=` on the commerce settings screen for every manager (plan security-findings-2026-10 item 6, owner decision 2026-10-28).

## How do running sites keep taking payments?
- No migration step: a plain value still reads as it is (`pg_gateway_secret_open()` returns it), and the next save of the commerce settings seals it.
- `init.php` defines the constants the gateway code reads (`submit_order.php`, `recurring_payment_job.php`, `get_express_order.php`, `get_order_preview.php`, `includes/panel/commerce.php`, `includes/payments/iyzico.php`) through `pg_gateway_secret_value()`, so none of those readers changed. A sealed value that does not open gives `''` (the gateway refuses, nothing leaks).
- Without `ENCRYPTION_KEY` nothing can be sealed and the value stays plain, as before.

## What does the settings screen do?
- `includes/settings/prep.php` prints `pg_gateway_secret_shown()`: the mask `********` when something is stored, else empty.
- `pg_gateway_secret_for_save()`: a blank or masked box keeps the stored secret (a sealed one written back unchanged, so no new IV and no audit line on every save); a new value replaces it; a sealed value that no longer opens is left alone, never wiped. There is no way to clear one from the box.
- Encryption key reset (same save): the kept secrets are opened with the old key and sealed with the new one, since the `ENCRYPTION_KEY` constant still holds the old key in that request.

## Why the 47-character limit?
- A sealed value is `32 + 4 × ceil(padded / 3)` characters; a 100-wide column fits 47 plain characters, the 255-wide one 159 (`pg_gateway_secret_max_length()`). A longer new value is refused with a field error before anything is written; a longer plain value kept from before stays plain. Real keys are 16–32 characters.

## What is left?
- The readers still get the secret as a constant, the one exception to `core/security-helpers-and-secret-storage.md`. Moving the decode into each reader waits for `submit_order.php` to be free.
- Other legacy gateways' keys (Stripe, Sage, ClearCommerce password) are still printed.

Related: docs/memory/core/security-helpers-and-secret-storage.md, docs/memory/commerce/payment-driver-iyzico.md
