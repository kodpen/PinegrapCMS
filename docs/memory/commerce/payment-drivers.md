---
name: payment-drivers
description: Payment driver layer (2026.11.0, PR #740; iyzico on it since step 2): pg_payment_<key>_ contract, pg_payment_call() answers, card vs redirect, add-ons, settings in connect_apps or config
---

## What is a payment driver?
- A file `includes/payments/<key>.php` (or an add-on file, absolute path, `pg_payment_driver_register()`) defining `pg_payment_<key>_<op>()`. Contract: `includes/payments/driver.php`; registry, settings, checkout helpers: `includes/fn/payments.php`. Function tables, not an interface: what is not defined is not supported.
- Required: `_capabilities()` (subset of `pg_payment_capability_names()`: installments, wallets, recurring, saved_cards, partial_refund, threeds). Optional: `_fields()` and the operations `prepare`, `authorize`, `complete`, `capture`, `refund`, `cancel`, `notify` (argument counts in `pg_payment_operations()`). `pg_payment_can($key, $op_or_capability)` asks before offering.
- Key: lowercase, letter first, single underscores, 2–32 chars (`__` splits key and field in input names).

## What do callers get back?
- Always `pg_payment_call($key, $op, $args)`: pads/cuts args, catches `Throwable` (`raw.exception`, `raw.message`), never throws. `pg_payment_answer()` fills every key in the driver.php header; besides money: `installments`, `card_brand`, `log` (lines the caller writes, failure or not). A failure: the service's words in `raw.message`.
- A success needs a status the operation may have (`pg_payment_operation_statuses()`), `success === true`, an `https://` address or `html` for `redirect`, `order_reference` for `notify`; else a failure, so no order is recorded paid on an unaccountable answer.
- Amounts: store hundredths; `pg_payment_decimal()` = legacy `%01.2lf` text, `pg_payment_hundredths()` rounds. Zero-decimal: `api_currency_minor_units()`.
- A driver never reads `$_POST`/session or writes order tables; the caller passes, records and keeps `state` (checkout: session `ecommerce.payment_state.<key>`; no state ends the wait). `complete($order, $amount, $return, $state)`: `$amount` = what the order totals now.

## Card or redirect?
- `card`: takes over a Payment Gateway value (`config.ecommerce_payment_gateway`, an ENUM). `pg_payment_legacy_gateways()` = the eight values in the settings list and the ENUM (a test keeps them equal), frozen in `submit_order.php`. A driver may take one over (iyzico → `Iyzipay`); a new value needs a migration step appending it to the ENUM.
- `redirect`: its own payment method (`orders.payment_method`, also an ENUM; `Credit/Debit Card`, `PayPal Express Checkout`, `Offline Payment` refused), own switch.
- `pg_payment_driver_for_gateway()` / `_for_method()`; registration refuses a taken key, gateway or method. `log_name` (optional): its name in log lines, default `label`.

## Where are the settings?
- `connect_apps` row `payment_<key>`: secrets sealed as one map (`pg_connect_seal()`), others in `options`, `enabled` = redirect switch; no schema per driver. Read: `pg_payment_setting($key, $name)`.
- `'settings' => 'config'` (shipped drivers only; add-ons get `connect`): keeps the config columns and switch the card already draws; no card or redirect rows for it (iyzico).
- Reset Encryption Key does not re-seal `connect_apps`; the card warns.

## What does the Ecommerce card do?
- Draws `pg_payment_settings_gateway_options()`, `_card_rows()` in the gateway box (`data-pg-payment-gateway`), `_redirect_rows()` after PayPal Express; never secrets.
- Save: `pg_payment_settings_check()` before the card's UPDATE (marks refuse it), `_save()` after. `pg_payment_settings_decide()` (pure): only drivers whose `payment_<key>__present` was posted; empty secret keeps; required only when active; no row while all values are defaults.

## How was it verified?
- `tests/payment_drivers_test.php`; sandbox page and dialog paths with a registered test driver.

Related: docs/memory/commerce/payment-driver-iyzico.md, docs/memory/commerce/payment-refunds.md, docs/memory/plans/payment-driver-layer.md, docs/memory/platform/sms-provider-layer.md
