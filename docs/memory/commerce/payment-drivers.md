---
name: payment-drivers
description: Payment driver layer (2026.11.0, PR #740; iyzico on it since step 2): pg_payment_<key>_ contract, pg_payment_call() answers, card vs redirect, add-ons, settings in connect_apps or config
---

## What is a payment driver?
- A file `includes/payments/<key>.php` (or an add-on file, absolute path, `pg_payment_driver_register()`) defining `pg_payment_<key>_<op>()`. Contract: `includes/payments/driver.php`; registry, settings, checkout helpers: `includes/fn/payments.php`. Function tables, not an interface: what is not defined is not supported.
- Required: `_capabilities()` (of `pg_payment_capability_names()`; `hosted_fields` = card typed in the service's fields, token posted). Optional: `_fields()`, operations `prepare`, `authorize`, `complete`, `capture`, `refund`, `cancel`, `notify` (`pg_payment_operations()`). `pg_payment_can($key, $what)` asks first.
- Key: lowercase, letter first, single underscores, 2–32 chars (`__` splits key and field in input names).
- `<key>_return` mode only with `complete()`: it skips CSRF and field checks (#937).

## What do callers get back?
- Always `pg_payment_call($key, $op, $args)`: pads/cuts args, catches `Throwable` (`raw.exception`, `raw.message`). `pg_payment_answer()` fills every key in the driver.php header (`installments`, `card_brand`, `log` lines the caller writes). A failure: the service's words in `raw.message`.
- A success needs a status the operation may have (`pg_payment_operation_statuses()`), `success === true`, an `https://` address or `html` for `redirect`, `order_reference` for `notify`; else a failure, so no order is recorded paid on an unaccountable answer.
- Amounts: store hundredths; `pg_payment_decimal()` = legacy `%01.2lf` text, `pg_payment_hundredths()` rounds. Zero-decimal: `api_currency_minor_units()`.
- A driver never reads `$_POST`/session or writes orders; the caller passes, records and keeps `state` (checkout: session `ecommerce.payment_state.<key>`; no state ends the wait). `complete($order, $amount, $return, $state)`: `$amount` = what the order totals now.

## Card or redirect?
- `card`: takes over a Payment Gateway value (`config.ecommerce_payment_gateway`, an ENUM). `pg_payment_legacy_gateways()` = the eight values in the settings list and the ENUM (a test keeps them equal), frozen in `submit_order.php`. A driver may take one over (iyzico → `Iyzipay`); a new value needs a migration step appending it to the ENUM.
- `redirect`: its own payment method (`orders.payment_method`, also an ENUM; built-in values refused), own switch; offered by `pg_payment_checkout_methods()` (`payment-method-lists.md`).
- `pg_payment_driver_for_gateway()` / `_for_method()`; registration refuses a taken key, gateway or method. Optional `log_name` (log lines; default `label`), `csp` (hosts for the policy).

## Where are the settings?
- `connect_apps` row `payment_<key>`: secrets sealed as one map (`pg_connect_seal()`), others in `options`, `enabled` = redirect switch. Read: `pg_payment_setting($key, $name)`.
- `'settings' => 'config'` (shipped drivers only; add-ons get `connect`): keeps the config columns and switch the card already draws; no card or redirect rows for it (iyzico).
- Reset Encryption Key does not re-seal `connect_apps`; the card warns.

## What does the Ecommerce card do?
- Draws `pg_payment_settings_gateway_options()`, `_card_rows()` (gateway box), `_redirect_rows()` (after PayPal Express); never secrets.
- Save: `pg_payment_settings_check()` before the card's UPDATE (marks refuse it), `_save()` after. `pg_payment_settings_decide()` (pure): only drivers whose `payment_<key>__present` was posted; empty secret keeps; required only when active; no row while all values are defaults.

## How was it verified?
- `tests/payment_drivers_test.php`; sandbox with a registered driver.

Related: commerce/payment-driver-iyzico.md, payment-driver-stripe.md, payment-driver-paypal.md, payment-refunds.md, plans/payment-driver-layer.md, platform/sms-provider-layer.md
