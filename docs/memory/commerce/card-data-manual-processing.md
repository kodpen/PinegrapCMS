---
name: card-data-manual-processing
description: Card data with no payment gateway (manual processing): the security code never stored, the number encrypted only, masked once the order is processed; refusal without openssl; hourly sweep
module: ecommerce
---

## What does the checkout store for a card order?
- `submit_order.php` writes `orders.card_verification_number = ''` always: the security code is never stored, masked or not (owner decision 2026-10-28, `plans/security-findings-2026-10.md` item 3).
- A gateway took the payment (`$transaction_id` set): the number masked (`protect_credit_card_number()`, last four digits).
- No gateway (`ECOMMERCE_PAYMENT_GATEWAY == ''`, staff run the card by hand): the number AES-CBC encrypted (`encrypt_credit_card_number()`); without encryption it is stored as `''`, never in clear.

## What happens without openssl or ENCRYPTION_KEY?
- The checkout refuses the card at validation (`credit_card_encryption_is_available()` false and no gateway): an error on `card_number`, "Card payments cannot be accepted right now. Please choose another payment method." Before, the number was stored in clear.

## When is the number cleared?
- Once the order is processed: it leaves `complete` (exported or cancelled). `pg_order_card_data_clear($order_id)` masks the number (decrypts first; unreadable → `''`) and empties the code, with an audit line (both columns are masked in `audit_log`).
- At once: the two order exports in `view_orders.php` (they mark the order exported) and Remove Card Data (`edit_orders.php`).
- Every other path (list status edit, external API, Workspace, ShipWorks): `pg_order_card_data_sweep()` in `job.php`, hourly (`data/temp/card_data_swept.txt`), up to 10 × 200 orders, over `pg_order_card_data_sweep_where()` (any code, or an unmasked number of a status other than incomplete/complete).

## What did running sites get?
- Step `upgrade_2026_11_0_card_security_codes()` (11.0.56): one `UPDATE orders SET card_verification_number = ''` on every stored code; data only, rerunnable. Numbers of already processed orders are masked by the job's first sweep.

## What is not covered?
- The checkout's liveform session still holds the posted code until the form is cleared (session only, never a table).
- The six legacy gateways are frozen; their requests still send the code to the gateway, which is the payment itself.

Tests: `tests/card_data_test.php` (helpers, the sweep condition, a source check that no file writes the code from a value).
