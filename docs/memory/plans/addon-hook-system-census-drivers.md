---
name: addon-hook-system-census-drivers
description: Add-on hook system AH1 census, commerce drivers: how payment drivers, carrier label drivers and marketplace connectors are found and loaded today, and what an outside driver would need
---

## What do these families share?
- No add-on loader exists: no `includes/addons/`, no code naming add-ons. Payments and carrier labels already have a register function for an outside driver with an absolute file path; nothing calls either yet. Registration is a static array per request, so something must call it every request before the first `pg_*_drivers()` call.
- Other families (SMS, e-document, jobs, address autocomplete and the rest): `addon-hook-system-census-registries.md`.

## How is a payment driver found?
- Contract: `includes/payments/driver.php`, functions `pg_payment_<key>_<op>`, `capabilities` required, other operations in `pg_payment_operations()`; callers use `pg_payment_call()` / `pg_payment_can()`. Key rule in `pg_payment_key_valid()`.
- Shipped: `pg_payment_builtin_drivers()` (`includes/fn/payments.php`): `iyzico`, `pay_with_iyzico`, `stripe`, `paypal`, `paytr`. `pg_payment_drivers()` = shipped + `pg_payment_registered_drivers()`; `pg_payment_load()` requires the definition's file and checks `pg_payment_<key>_capabilities`.
- Settings: `connect_apps` row `payment_<key>`, secret fields sealed; `'settings' => 'config'` only for shipped drivers.
- Outside driver: `pg_payment_driver_register($key, [label, kind, gateway|method, file, portal, log_name, csp])` refuses a taken key or value and the reserved legacy methods. CSP (`pg_payment_csp_sources()`), `payment_callback.php?driver=<key>`, return modes and `submit_order.php` follow the definition with no per-key edit.
- Blocker: the value must exist in the ENUM `config.ecommerce_payment_gateway` (card) or `orders.payment_method` (redirect); the non-strict session stores an unknown value as `''`. Each shipped driver appended it in a migration step (`includes/migrations/2026.11.0.php`).
- Still hard-coded per shipped driver: iyzico return modes, `pg_order_pending_payment_methods()`, the WAF good-bot table.

## How is a carrier label driver found?
- Contract: `pg_label_<key>_<op>` (`includes/fn/carrier_labels.php`, files in `includes/shipping/`), `info` required (carriers must be `pg_shipping_carriers()` keys, formats pdf/zpl/svg); `fields`, `test`, `create`, `label`, `cancel`, `track`, `pickup`, `rate` optional; callers `pg_label_call()` / `pg_label_can()`.
- Shipped: `pg_label_builtin_drivers()`: `geliver`, `basitkargo`, `shippo`, `sendcloud`, `aras`, `kargonomi`; `pg_label_drivers()` adds `pg_label_registered_drivers()`; `pg_label_load()` as for payments.
- Settings: `connect_apps` `label_<key>`.
- Outside driver: `pg_label_driver_register($key, [label, file, portal])`; no ENUM (`shipment_labels.driver`, `shipping_methods.label_driver` are VARCHAR(32)); tracking, pickup, auto label and the method's Carrier Service picker all read `pg_label_drivers()`. A new carrier (not service) still needs a `pg_shipping_carriers()` entry (`includes/fn/ecommerce.php`).
- Carrier webhooks: `carrier_webhook.php?driver=<key>` resolves optional `pg_label_<key>_webhook_read/_verify/_register/_remove/_manual` through `pg_carrier_webhook_function()`; no per-key list.

## How is a marketplace connector found?
- Contract: `mp_<provider>_connector()` returns a capability-to-function table (`test_credentials` required); callers `mp_call()` / `mp_can()` (`includes/api/outbound/connectors/base.php`).
- Shipped: `mp_providers()`, a hard-coded array: `n11`, `trendyol`, `merchant_center`. `mp_connector()` requires `dirname(__FILE__) . '/' . file`, so the file must sit in the connectors folder.
- Settings: `marketplace_accounts` rows (`provider` VARCHAR(30), credentials encoded); fields declared in the `mp_providers()` entry.
- Outside connector: no register function; it needs an `mp_providers()` entry and a loader that takes an outside path. Webhooks (`marketplace_webhook.php?account=<id>`) route by account row.
