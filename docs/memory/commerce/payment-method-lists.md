---
name: payment-method-lists
description: Where checkout, panel and ERP list payment methods by hand (checked 2026-10-26): what a new redirect driver's method must be added to today, and the Pay With Iyzico gaps found on the way
---

## Is a redirect driver offered at checkout by itself?
- No. The settings card stores an `enabled` switch per `'connect'` redirect driver (`pg_payment_settings_redirect_rows()`), but nothing at checkout reads it or loops over `pg_payment_drivers()`. Each screen names its methods and the config switch of each (`ECOMMERCE_PAY_WITH_IYZICO` for Pay With Iyzico, `init.php`).
- Generic already: the driver branch of submit_order.php, the return mode → method assignment atop it, `pg_payment_return_modes()` (`<key>_return`), the receipts and order view (they print the stored value), the external API (raw value, no enum in the schema).

## Which lists name the methods?
- submit_order.php: `$known_payment_methods` (a translated label mapped back to its value), `$allowed_payment_methods` (offered for this order, from the config switches; redirect methods only without a recurring line), and the "nothing to prepare" `case 'Pay With Iyzico': case 'Offline Payment':` whose `default` refuses.
- Designer express order widget (includes/fn/widgets_express_order.php): the radio list builder and the whole payment block's builder; the label is `lang($method)`, so the value needs a Turkish key.
- Order preview: get_order_preview.php (the block condition, the radios, the custom-layout flags) and includes/templates/order_preview.php.
- order_preview.php: the CSRF skip and the page id check name the three old return modes instead of `pg_payment_is_return_mode()`, so a new `<key>_return` back to order preview fails the token check (express_order.php is generic).
- Classic express order page (get_express_order.php, its template): card, PayPal Express, Offline, On Account only.
- frontend.src.js `init_payment_method()`: only PayPal Express swaps the button label.
- Panel and documents: view_orders.php (filter WHERE, selected option, options), view_order_report.php (`value_options`), `pg_legal_document_payment_methods()`, `erp_payment_method_code()` (e-Arşiv ODEMEARACISI), the receipt label map in includes/fn/widgets.php (falls back to the value).
- The value itself: `orders.payment_method` is an ENUM; a step of the open version appends it as 11.0.24 did for the gateway (`install_column_info()`, `install_modify_column()`); the install dump needs no edit (a clean install runs the upgrades).
- CSP: `pg_payment_csp_sources()` adds only the card driver's hosts; a redirect driver with scripts or a frame (PayTR's iframe) needs more.

## What gaps did the check find?
- Pay With Iyzico is missing from view_orders.php's filter and view_order_report.php's options, and the classic express order page never offers it.
- get_order_preview.php's condition for drawing the payment block leaves Pay With Iyzico out: a store whose only method it is shows no payment block on order preview (unless gift cards are on).

Related: docs/memory/commerce/payment-drivers.md, docs/memory/commerce/payment-driver-iyzico.md, docs/memory/commerce/order-payment-method-normalization.md, docs/memory/plans/payment-driver-layer.md
