---
name: payment-gateway-secrets
description: Legacy gateway secrets sealed in config (pgenc2, AES-256-GCM since #PR; pgenc1 still read), drawn masked, opened in init.php; plain values still read; the 41-character limit
module: payments
---

## Which secrets, and how are they stored?
- `pg_gateway_secret_fields()` (`includes/fn/payments.php`): `ecommerce_authorizenet_transaction_key`, `ecommerce_paypal_payflow_pro_password`, `ecommerce_paypal_payments_pro_api_password`, `ecommerce_paypal_express_checkout_api_password` (VARCHAR 100) and `ecommerce_iyzipay_secret_key` (VARCHAR 255).
- Each column holds `pgenc2:<base64 of iv(12) + tag(16) + ciphertext>` (AES-256-GCM, key = HMAC-SHA256 of `pinegrap-gateway-secret-v2` with `ENCRYPTION_KEY`, `pg_gateway_secret_key()`; `pg_gateway_secret_seal()` / `pg_gateway_secret_open()`). A wrong key or a changed byte fails the tag and opens to `null`. The prefix tells it from a plain value, which may contain `:`.

## Why not the first form any more?
- The first form (#2791, 2026-10-28) was `pgenc1:<base64 ciphertext>:<base64 iv>`, AES-256-CBC with key = sha256 of `ENCRYPTION_KEY` and no MAC. A wrong key passed the PKCS#7 padding check about once in 256 tries and returned garbage instead of `null`: a test failed at random on CI (#3025, #3039) and after a key reset a gateway could get garbage instead of `''`. Replaced in #PR.
- `pgenc1:` values still open with the right key (dev/test sites hold them); a wrong key can still give garbage for them. Any commerce settings save that keeps one (blank or masked box) re-seals it as `pgenc2:`; a first-form secret too long for the new form (42–47 characters in a 100-wide column) stays `pgenc1:` under the same key, and goes plain on a key reset (the too-long rule below).
- Before #2791 they were plain and printed into `value=` on the settings screen (owner decision 2026-10-28).

## How do running sites keep taking payments?
- No migration step: a plain value still reads as it is (`pg_gateway_secret_open()` returns it), and the next save of the commerce settings seals it.
- `init.php` defines the constants the gateway code reads (`submit_order.php`, `recurring_payment_job.php`, `get_express_order.php`, `get_order_preview.php`, `includes/panel/commerce.php`, `includes/payments/iyzico.php`) through `pg_gateway_secret_value()`, so none of those readers changed. A sealed value that does not open gives `''` (the gateway refuses, nothing leaks).
- Without `ENCRYPTION_KEY` nothing can be sealed and the value stays plain, as before.

## What does the settings screen do?
- `includes/settings/prep.php` prints `pg_gateway_secret_shown()`: the mask `********` when something is stored, else empty.
- `pg_gateway_secret_for_save()`: a blank or masked box keeps the stored secret (a `pgenc2:` one written back unchanged, so no new IV and no audit line on every save); a new value replaces it; a sealed value that no longer opens is left alone, never wiped. There is no way to clear one from the box.
- Encryption key reset (same save): the kept secrets are opened with the old key and sealed with the new one, since the `ENCRYPTION_KEY` constant still holds the old key in that request.

## Why the 41-character limit?
- A sealed value is `7 + 4 × ceil((28 + length) / 3)` characters (GCM has no padding); a 100-wide column fits 41 plain characters, the 255-wide one 158 (`pg_gateway_secret_max_length()`; the first form fitted 47 and 159). A longer new value is refused with a field error before anything is written; a longer plain value kept from before stays plain. Real keys are 16–32 characters.

## What is left?
- The readers still get the secret as a constant, the one exception to `core/security-helpers-and-secret-storage.md`. Moving the decode into each reader waits for `submit_order.php` to be free.
- Other legacy gateways' keys (Stripe, Sage, ClearCommerce password) are still printed.

Related: docs/memory/core/security-helpers-and-secret-storage.md, docs/memory/commerce/payment-driver-iyzico.md
