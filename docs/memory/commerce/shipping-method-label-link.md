---
name: shipping-method-label-link
description: A shipping method linked to a carrier label service (11.0.177, #8403): label_driver and label_service, the Carrier Service picker, the carrier it sets, API fields, Create Label
module: shipping_methods
---

## What is stored?
- Step 11.0.177 (`upgrade_2026_11_0_shipping_method_label_link()`, decided in `workflow/decisions-made-schema-13.md`, #8375): `shipping_methods.label_driver` VARCHAR(32) and `label_service` VARCHAR(64), both `''` by default; `shipping_rates.service` ENUM → VARCHAR(100), old values kept, MODIFY only while not a VARCHAR (rerun is a skip).
- `label_service` `''` = the cheapest offer. The quote charged at checkout: `shipping-label-checkout-rate.md`; the label once paid: `shipment-label-auto.md`.

## How does the method screen pick it?
- "Carrier Service" (`pg_shipping_method_label_field()`, `includes/forms/shipping_method_form.php`) lists the drivers of `pg_shipping_method_label_drivers()`: on, ready, can `create` (`pg_shipment_label_drivers()`), each with its services from `_info()['services']` (code => name, cleaned by `pg_shipping_method_label_services_clean()`). Without a list the option takes a typed "Service Code" box (`label_service_code`, shown by `init_shipping_method_service()` in `backend.src.js`).
- A stored link whose driver is not ready now, or a service the list lost, is offered back on its own, so a plain save keeps it. Nothing ready and no link: a hidden empty `label_link`.
- Post → columns: `pg_shipping_method_label_link_decide()` (pure; an unknown driver key is dropped).

## When is the Real-Time Rate switch allowed?
- `pg_shipping_method_has_realtime_rate($service, $label_driver)`: a link, or a USPS/UPS service (FedEx none). Used by the screen, the list's quick edit (`shipping_method_list_edit.php`, which keeps the switch of a linked method when its service is cleared) and the API. The switch is drawn for a link too, with words saying it adds the live price and a method without a price is not offered (`shipping-label-checkout-rate.md`).

## Which carrier does a linked method get?
- `pg_shipping_method_save()` → `pg_shipping_method_link_save_columns()`: `carrier` = the first word (`[^a-z0-9]` split, lower case) of the service code when the driver's `carriers` hold it (`YURTICI_STANDART` → `yurtici`), else `''`; removing a link clears the carrier it set; a carrier on an unlinked method stays.
- Before the upgrade has run (files land first) the link columns are left out of the write (`pg_schema_has()`).

## What does the API say?
- `label_driver` (string?, null = none; a key `pg_label_driver()` does not know is refused) and `label_service` (string, cleared with the driver) on read, create and update; `realtime_rate` follows the same rule as the screen.

## How does Create Label follow it?
- `pg_shipment_label_create_check()` reads the method's link (probed) and takes `pg_shipment_label_method_driver()`: the linked driver while it is in `pg_shipment_label_drivers()`, else today's carrier pick. The shipment's `service` is `pg_shipment_label_method_service()`: the linked service for the linked driver, else `''` (the account's). Bulk create and the API's label route use the same check. Return labels do not follow it.

## How was it verified?
- `tests/shipping_method_label_link_test.php`, plus the form, list quick edit and API shipping method tests. Not run on a dev DB in the PR session (no database there).
