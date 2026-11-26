---
name: settings-ecommerce-store-payments
description: Site Settings › Ecommerce, the Store and Payment methods rows opened (EC2): groups, the pane's drawing helpers, what moved into Store, the gateway script kept
module: commerce_settings_fold
---

## How are Store and Payment methods drawn?
- No popover, no `div.switch`, no `collapse-switcher`, no plain radio: every field is drawn under its switch whatever the switch says (as `ui/settings-appearance.md`).
- Pure helpers in `includes/fn/commerce_settings_fold.php`: `pg_commerce_settings_switch()` (a `pg-switch`, one help line), `pg_commerce_settings_group_switch()` (a switch drawn as the group's label), `pg_commerce_settings_group()`, `pg_commerce_settings_seg()` (a `pg-seg` posting the radios' old values and ids), `pg_commerce_settings_ticks()` (`pg-checkbox` ticks in a wrapped line). Test: `tests/settings_commerce_store_payments_test.php`.

## What does each row hold?
- Store: Next order number, Commerce e-mail address, Folder for product images; group "Prices and tax" (Multi-currency, Tax, Allow tax-exempt, Tax-exempt label); group "Products" (Show product images in tables, Photo reviews, Barcodes, Default barcode type, the four Custom product field labels); group "Labels" (Create a label only after the pack is checked), drawn only with the pack tables.
- Payment methods: groups headed by their switch: Cards (Accepted cards as ticks, Payment gateway, then the gateway's rows), Pay with iyzico, PayPal Express Checkout (Transaction type and Mode as `pg-seg`, its connection), each redirect driver (`pg_payment_settings_redirect_rows()`), Offline payments; then group "After the order" (Grant private access, Page after a reorder or retrieved order).

## What moved, and what stayed?
- `pack_before_label` left Shipping and `ecommerce_custom_product_field_1..4_label` left Payment methods for Store; anchors `pack_before_label` and `ecommerce_custom_product_field_1_label` open Store (`pg_settings_legacy_anchors()`). Store's search words gained product images, photo reviews, custom product field, label, pack.
- The gateway's rows keep their ids (`ecommerce_payment_gateway_transaction_type_row`, `_mode_row`, `ecommerce_paypal_payments_pro_gateway_mode_row`, `ecommerce_iyzipay_*_row`) and inline display: `show_or_hide_ecommerce_payment_gateway()` still switches them; the Cards switch now calls it too, so turning cards on shows the gateway's rows at once.
- Field names, values, card ids and `commerce.save.php` unchanged; one Save.

Related: docs/memory/plans/settings-ecommerce-tidy.md, docs/memory/ui/settings-fold-rows.md
