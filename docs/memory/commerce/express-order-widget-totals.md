---
name: express-order-widget-totals
description: Express order widget totals (designer pages) as submit_order() charges them: today's lines, order discount and its tax, gift cards, surcharge, shipping a kurus lower (pg_express_order_charged_totals)
---

## Where do the widget's totals come from?
- `_render_system_widget_express_order()` (includes/fn/widgets_cart.php) builds the totals table, the `^^__cart_*^^` tokens, the `has_discount` / `has_gift_card` flags and the posted `total` / `total_with_surcharge` from `pg_express_order_charged_totals()` (pure, same file), in `submit_order()`'s order: the lines charged today (`pg_order_line_charged_today()`), their tax (never below 0), the session's `order_discount` (set by `apply_offers_to_cart()`, which the widget runs first) at most today's subtotal (`pg_order_charged_discount()`), the tax less the discount's share, the shipping of the recipients, the applied gift cards (`applied_gift_cards.old_balance`, at most the total, none on an order of gift cards alone), then the surcharge on what is left.
- The tax on the lines comes from the tax pass the widget runs on the address its form shows (`express-order-tax-address.md`). The orders row's `discount` and `gift_card_discount` columns are written only by `submit_order()`, so they are not read.

## What was the symptom? (#985, PR #1126)
- The widget read the discount and the gift card from those columns (always 0 at checkout) and summed every line, a subscription that starts later included. The visitor saw and confirmed ₺30,00 while ₺27,00 (10% off) or ₺22,00 (with a ₺5 gift card) was charged, or ₺60,00 for a cart charging ₺10,00 today. `submit_order()` refuses only a higher posted total, so nothing complained.
- Checked on the sandbox by rendering the widget from the command line: 3 × ₺14,95 with a ₺5 gift card posted ₺52,92 before and ₺47,92 now; with a subscription starting in 30 days ₺147,26 before, ₺52,92 now.

## How does the widget show a shipping charged a kurus lower? (#1211)
- `pg_express_order_charged_totals()`'s `$settle_rates` lowers the shipping and the surcharge where a rate's total is out of reach (`pg_order_charges_reachable()`, erp/erp-order-charges-vat.md). The shipping script gets the stored shipping, the charged one and the total shown (`_eo_render_widget_js()`'s `$totals`): while the methods the page was drawn with stay picked it shows the charged shipping, and another pick moves the total by the shipping's difference. It used to add the displayed subtotal, tax and shipping on load, which dropped the order discount (52.92 after 5.00 off showed 57.93) and put 9.99 back.

## Does the posted total follow a picked shipping method? (#3579)
- Yes. The picked method's cost reaches `ship_tos.shipping_cost` only with the POST, so the page is drawn with the old shipping; the shipping script's `recomputeSidebarTotal()` (`_eo_render_widget_js()`, includes/fn/widgets_express_order.php) moves the shown total and writes the hidden `total` (drawn posted total + picked − charged shipping) and `total_with_surcharge` (that plus the card surcharge taken again with `SURCHARGE_UNITS`, `pg_percent_of()`'s rounding). While the drawn shipping stays picked, the drawn figures stay.
- Symptom: only the shown total moved; `submit_order()` found its total above the posted one and refused the first "Complete Order" of every paid method ("the total has changed"). Its check is unchanged; a template without the total hook classes moves neither total and still gets one refusal.

## What is not shared?
- The classic express order page (`get_express_order.php`) and `submit_order()` keep their own copies of the same arithmetic; the widget's is the one with a test (`tests/express_order_totals_test.php`).

Related: docs/memory/commerce/order-discount-charged-today.md, docs/memory/commerce/express-order-tax-address.md
