---
name: express-order-tax-address
description: Which address the express order widget takes its tax on: prefill at render, eo_tax_totals on each address edit; no ship_tos write (#3326, PRs #3530, #3920)
module: widgets_cart, ecommerce
---

## Which address does the page take the tax on? (#3326)
- `_render_system_widget_express_order()` (includes/fn/widgets_cart.php) loads the order's `ship_tos` rows and runs `_eo_prefill_billing_fields()` and `_eo_prefill_shipping_fields()` (widgets_express_order.php) **before** the tax pass, once each: the visitor's value, then the order / contact / address book, then the default country.
- `pg_express_order_tax_addresses()` (pure, same file, `tests/express_order_tax_address_test.php`) turns the prefilled values into the taxed addresses: a recipient takes the form's country and state when the form holds a country, else its stored row; billing takes the form's, or the one recipient's address when "billing same as shipping" is ticked (mirrors express_order.php's copy).
- The pass is `pg_express_order_tax_pass()` and the totals `pg_express_order_live_totals()` (widgets_cart.php), shared by the render and the live action below.
- Recipients are taxed through `update_order_item_taxes($ship_to_addresses)` (ship_to_id => country, state). Nothing is written to `ship_tos`: the shipping method, `complete` and the order screens read that row, and it gets the address only on submit. Every other caller passes nothing and keeps the stored rows.
- The billing address is written to `orders.billing_country` / `billing_state` from the prefilled values (the tax pass reads it there for lines without a recipient).

## What was the symptom?
- The recipient row is created empty and the widget seeded only the billing country, so every shipped line was taxed 0. The page showed and posted 0 tax, `submit_order()` recomputed with the posted address and refused the first "Complete Order" ("the total has changed").
- The seed wrote the default country into the order before the prefill read it, hiding a signed-in customer's contact country.

## What happens when the visitor edits the address? (PR #3920)
- The page script (`_eo_render_widget_js()`) posts `eo_tax_totals` to `api.php`, debounced 600 ms, on a change of `billing_country`, `billing_state`, `billing_same_as_shipping` or `shipping_<id>_country` / `_state`. It is wired before the script's "no recipients" return, so a cart with nothing to ship is covered.
- `pg_panel_eo_tax_totals()` (panel/commerce.php; public, no login, no token) works only on `$_SESSION['ecommerce']['order_id']` while that order is `incomplete`; with tax off or no order it answers `status: unchanged` and writes nothing. It reads only the order's own recipients' fields (`pg_express_order_live_tax_fields()`), runs the shared pass (billing address on the order, the lines' taxes), never writes `ship_tos` or the liveform session, and answers tax, total, total_with_surcharge, surcharge, shipping_drawn, shipping in base minor units (`pg_express_order_live_tax_answer()`, `tests/express_order_live_tax_test.php`).
- The script then writes every `.pg-eo-tax-formatted`, the total span and the hidden `total` / `total_with_surcharge`, makes the answer the new baselines (`TOTAL_SHOWN`, `POSTED_TOTAL`, `SHIP_DRAWN`, `SHIP_CHARGED`) and runs `recomputeSidebarTotal()` again for a method picked on the page (the answer counts the stored shipping only). A template without the hook classes gets the server's figures and no shipping move, as before.
- A tax row the template left out (`has_tax` false because the tax was 0 when drawn) stays out until the next render; the total still moves. An answer overtaken by a later edit, a refusal or a network error leaves the page as it is; `submit_order()` still refuses a higher total.

## What is left?
- Picking a paid shipping method moves only the visible total; `ship_tos.shipping_cost` is 0 until the first POST, so paid shipping may still refuse the first submit (not verified).
- The classic `get_express_order.php` is not touched (workflow/decisions-made-product.md).
