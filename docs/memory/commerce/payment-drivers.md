---
name: payment-drivers
description: Payment driver layer (2026.11.0): pg_payment_<key>_ contract, pg_payment_call() answers, card vs redirect drivers, add-on registration, settings in connect_apps payment_<key>
---

## What is a payment driver?
- A file `includes/payments/<key>.php` (or an add-on file registered with `pg_payment_driver_register($key, $definition)`, absolute path) defining functions `pg_payment_<key>_<op>()`. Contract and dispatch: `includes/payments/driver.php`; registry and settings: `includes/fn/payments.php` (loads driver.php). Function tables like the e-document and SMS layers, not a PHP interface: what is not defined is not supported.
- Required: `_capabilities()` (subset of `pg_payment_capability_names()`: installments, wallets, recurring, saved_cards, partial_refund, threeds). Optional: `_fields()` and the operations `prepare`, `authorize`, `complete`, `capture`, `refund`, `cancel`, `notify` (argument counts in `pg_payment_operations()`). `pg_payment_can($key, $op_or_capability)` asks before offering.
- Key: lowercase, letter first, single underscores, 2–32 chars (`__` separates key and field in input names).

## What do callers get back?
- Always `pg_payment_call($key, $op, $args)`: pads/cuts args to the contract count, catches `Throwable` (under `raw`), never throws. `pg_payment_answer()` fills every key listed in the driver.php header.
- A success needs a status the operation may have (`pg_payment_operation_statuses()`), `success === true`, an `https://` address or `html` for `redirect`, `order_reference` for `notify`; else it is a failure, so no order is recorded paid on an answer nobody can account for.
- Amounts are store hundredths: `pg_payment_decimal()` = legacy `sprintf('%01.2lf', $x / 100)`, `pg_payment_hundredths()` = `(int) round($x * 100)`. Zero-decimal currencies: `api_currency_minor_units()`.
- A driver never reads `$_POST`/session or writes order tables; the caller passes, records and keeps `state` server side.

## Card or redirect?
- `card`: takes over a Payment Gateway value (`config.ecommerce_payment_gateway`, an ENUM). `pg_payment_legacy_gateways()` = the eight values whose code stays frozen in `submit_order.php`; a test keeps it equal to the settings list and the ENUM. A driver may take one of them over (iyzico → `Iyzipay`); a new value needs a migration step appending it to the ENUM first.
- `redirect`: its own payment method (`orders.payment_method` value, also an ENUM; `Credit/Debit Card`, `PayPal Express Checkout`, `Offline Payment` refused) with its own switch.
- `pg_payment_driver_for_gateway()` / `_for_method()`; registration refuses a taken key, gateway or method.

## Where are the settings?
- `connect_apps` row `payment_<key>`: secret fields sealed as one map (`pg_connect_seal()`), other values in `options`, `enabled` = redirect switch (card: 1). No schema per driver. Driver reads `pg_payment_setting($key, $name)` (stored, else default).
- A driver with no `_fields()` draws nothing and keeps its own settings (a ported legacy gateway: its config columns).
- Reset Encryption Key does not re-seal `connect_apps`; the card then warns.

## What does the Ecommerce card do?
- Draws `pg_payment_settings_gateway_options()`, `_card_rows()` in the gateway box (`data-pg-payment-gateway`, toggled by `show_or_hide_ecommerce_payment_gateway()`), `_redirect_rows()` after PayPal Express. Secrets never drawn back.
- Save: `pg_payment_settings_check()` before the card's UPDATE (marks refuse the whole card), `pg_payment_settings_save()` after it. `pg_payment_settings_decide()` (pure, tested): only drivers whose `payment_<key>__present` was posted; empty secret keeps; required only for the selected gateway / switched-on redirect; no row while every value is a default.

## How was it verified?
- `tests/payment_drivers_test.php`; sandbox page and dialog paths with a registered test driver.

Related: docs/memory/plans/payment-driver-layer.md, docs/memory/platform/sms-provider-layer.md
