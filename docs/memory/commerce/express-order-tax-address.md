---
name: express-order-tax-address
description: Which address the express order widget takes its render-time tax on: prefill first, recipients through update_order_item_taxes() without a ship_tos write (#3326, PR #3530)
module: widgets_cart, ecommerce
---

## Which address does the page take the tax on? (#3326)
- `_render_system_widget_express_order()` (includes/fn/widgets_cart.php) loads the order's `ship_tos` rows and runs `_eo_prefill_billing_fields()` and `_eo_prefill_shipping_fields()` (widgets_express_order.php) **before** the tax pass, once each: the visitor's value, then the order / contact / address book, then the default country.
- `pg_express_order_tax_addresses()` (pure, same file, `tests/express_order_tax_address_test.php`) turns the prefilled values into the taxed addresses: a recipient takes the form's country and state when the form holds a country, else its stored row; billing takes the form's, or the one recipient's address when "billing same as shipping" is ticked (mirrors express_order.php's copy).
- Recipients are taxed through `update_order_item_taxes($ship_to_addresses)` (ship_to_id => country, state). Nothing is written to `ship_tos`: the shipping method, `complete` and the order screens read that row, and it gets the address only on submit. Every other caller passes nothing and keeps the stored rows.
- The billing address is written to `orders.billing_country` / `billing_state` from the prefilled values (the tax pass reads it there for lines without a recipient).

## What was the symptom?
- The recipient row is created empty and the widget seeded only the billing country, so every shipped line was taxed 0. The page showed and posted 0 tax, `submit_order()` recomputed with the posted address and refused the first "Complete Order" ("the total has changed").
- The seed wrote the site's default country into the order before the prefill read it, so a signed-in customer's contact country never showed in the billing country.

## What is left?
- No live recompute when the visitor edits the address on the page: a typed address that raises the tax still gets one refusal, which re-renders with the right tax.
- Picking a paid shipping method moves only the visible total; `ship_tos.shipping_cost` is 0 until the first POST, so paid shipping may still refuse the first submit (not verified).
- The classic `get_express_order.php` is not touched (workflow/decisions-made-product.md).
