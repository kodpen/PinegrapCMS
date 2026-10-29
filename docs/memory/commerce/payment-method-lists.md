---
name: payment-method-lists
description: One list of checkout payment methods (pg_payment_checkout_methods(), PR #1743): screens that read it, what a redirect driver still needs by hand, the Pay With Iyzico gaps kept
---

## Where is the list?
- `includes/fn/payments.php`: `pg_payment_builtin_methods()` (card, PayPal Express Checkout, Pay With Iyzico, Offline Payment: their config switches), `pg_payment_driver_methods($enabled_only)` (method => key of each `'connect'` redirect driver; Pay With Iyzico keeps its config switch and is not among them), `pg_payment_method_values()` (both, every value a checkout stores), `pg_payment_method_label()` (built-in: `lang()`; driver: its `label`).
- `pg_payment_checkout_methods_decide()` is the pure rule (`tests/payment_methods_test.php`): order card, PayPal, Pay With Iyzico, drivers, Offline; no redirect method (PayPal, iyzico, drivers) for a recurring order; Offline only when the order may be paid offline.
- `pg_payment_checkout_methods(array('recurring', 'offline_allowed', 'card_brands'))` reads the constants and the drivers' `enabled` switch. `pg_payment_checkout_driver_methods($recurring)` = only the drivers' part, method => label.

## Who reads it?
- submit_order.php: `$known_payment_methods` = `pg_payment_method_values()`; `$allowed_payment_methods` = `pg_payment_checkout_methods()`; the "nothing to prepare" switch lets an enabled driver's method through (its driver pays it).
- Designer express order widget (`_eo_render_payment_methods*()`): the whole list with `card_brands` (card only while a brand, Troy included, is on); no recurring or offline-allowed filter, as before.
- Classic order preview and express order (get_order_preview.php, get_express_order.php, both layouts and both templates): built-in methods keep their own markup; the drivers' methods get a radio after them (`$payment_driver_methods` in custom layouts), count in the block condition, the "only method" checks, the surcharge note and the "no payment method" error.
- order_preview.php: CSRF skip and page id check use `pg_payment_is_return_mode()`, as express_order.php.
- view_orders.php filter, view_order_report.php options, `pg_legal_document_payment_methods()`, `erp_payment_method_code()` (a driver's method is ODEMEARACISI): append the drivers' methods.

## What still needs a hand edit for a new method?
- `orders.payment_method` ENUM: a migration step appends it (`install_column_info()`, `install_modify_column()`).
- The receipt label map in includes/fn/widgets.php (falls back to the value). view_order_report.php's options append the drivers' methods since #1827.
- CSP: a driver's `csp` hosts are added for the card driver and, since #3036, for each switched-on redirect driver that frames its page (`pg_payment_csp_sources()`, `payment-driver-paytr.md`).
- frontend.src.js `init_payment_method()` swaps the button label only for PayPal and Pay With Iyzico; a driver's method keeps the standard label.
- A site's own custom layout draws a driver's radio only if it loops `$payment_driver_methods`.

## Which Pay With Iyzico gaps stay (owner decision, `workflow/decisions-made-product.md`)?
- Missing from view_orders.php's filter and view_order_report.php's options; the classic express order page never offers it.
- get_order_preview.php's block condition and its "no payment method" error leave it out. Kept: it would not reach customer sites; old sites get it by hand. The shared list is for forward compatibility.

Related: docs/memory/commerce/payment-drivers.md, docs/memory/commerce/payment-driver-iyzico.md, docs/memory/commerce/order-payment-method-normalization.md, docs/memory/commerce/payment-driver-layer-decisions.md
