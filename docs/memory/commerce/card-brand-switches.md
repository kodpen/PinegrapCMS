---
name: card-brand-switches
description: How the checkout reads the accepted card brand switches (Visa, MasterCard, American Express, ...) and why American Express was never counted
module: payments
---

## How are the card brand switches read?
- The brand constants (`ECOMMERCE_VISA`, `ECOMMERCE_AMERICAN_EXPRESS`, ...) are defined in `init.php` from the config row, so they hold the strings `'1'` / `'0'`, never booleans.
- `pg_payment_card_brand_config()` reads them (label => value) and `pg_payment_card_brands_accepted()` (pure) keeps the ones `== true`, in the settings form's order. `pg_payment_builtin_switches(true)` and the express order widget's brand pills use them.

## What was wrong?
- `ECOMMERCE_AMERICAN_EXPRESS === true` was never true: a store that accepted only American Express showed no Credit/Debit Card method on the order preview, the express order page and the express order widget, and American Express had no pill. `get_order_preview.php` and `get_express_order.php` now compare it with `==` like the other brands (#4629).
- Never compare a config constant with `=== true`.
