---
name: settings-ecommerce-shipping-stock
description: Site Settings › Ecommerce, the Shipping, Address autocomplete and Stock locations rows opened (EC3): groups, the carriers' and label services' drawing, Add a location, the default as a list
module: commerce_settings_fold
---

## How are the three rows drawn?
- As Store and Payment methods (`ui/settings-ecommerce-store-payments.md`): no popover, no `div.switch`, no `collapse-switcher`, no plain radio; every field under its switch whatever the switch says. Test: `tests/settings_commerce_shipping_test.php`.
- The functions that draw into them follow the same look: `pg_label_settings_rows()` (`carrier_labels.php`), `pg_carrier_api_settings_fields()` / `_test_button()` (`carrier_api.php`), `pg_addr_settings_fields()` (`address_autocomplete.php`). `geo.php`'s note is drawn on Firewall and Languages, not here.

## What does each row hold?
- Shipping: the Shipping switch; Recipient mode as `pg-seg` (Single recipient, Several recipients); group USPS (connection, Verify US addresses with Enforcement as `pg-seg`, the test); UPS and FedEx groups headed by their switch (connection, Account number, the test); group "Carrier labels", each label service headed by its `pg-switch` with its carriers as the help line; group "Messages"; End of day time with "Site time now" under it.
- Address autocomplete: the `pg-switch` (still disabled while the chosen provider is not connected), Provider, the provider's connection.
- Stock locations: one help line; the table (name, address, order, On as `pg-switch`); Default location as a select under it, posting `stock_location_default` as the radios did; "Add a location" opens the New location fields; Stock history.

## How does Add a location open?
- `pg_commerce_settings_reveal()` draws a quiet button and a `hidden` box `<id>_box`; `pg_commerce_settings_reveal_script()` (in `$pg_settings_scripts`, bound once on the document) toggles it and `aria-expanded`. The box posts its fields while closed; an empty name adds nothing.

## What stayed?
- Field names, ids, values, card ids and `commerce.save.php` unchanged; no anchor moved. The label services' hidden `present` input sits in their switch's cell.

Related: docs/memory/ui/settings-ecommerce.md, docs/memory/ui/settings-ecommerce-store-payments.md
