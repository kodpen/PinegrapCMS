---
name: cart-open-blocks
description: Shopping cart blocks opened (CB6c, #9901): applied offers, campaign gifts and the quick add box drawn on the canvas from twins of pure PHP builders, settings only
module: widgets_cart
---

## What did CB6c open?
- Step CB6c (#9901) of the closed-blocks opening ([[canvas-closed-blocks]]); the census: [[canvas-closed-blocks-widgets]]. The shop steps before it: [[catalog-detail-open-blocks]], [[catalog-listing-open-blocks]].
- The sections `applied_offers`, `pending_offers` and `quick_add` of a shopping cart widget, and the `applied_offers` content node the shop templates use: settings only, drawn true. Each depends on the request: the offers applied to this cart, the gifts it has earned, the products of the group the visitor can buy now (stock, prices, discounts).
- `pending_offers` is the campaign gifts box: gifts the cart qualifies for that wait for the visitor to press Add. A gift waits only when the site must ask where it goes (several recipients, a gift that ships); otherwise `apply_offers_to_cart()` places it by itself and it shows among the applied offers.

## What does the site build them with?
- Pure builders in `includes/fn/widgets_cart.php`: `pg_cart_applied_offers_html($labels)`, `pg_cart_pending_offer_recipient_html()`, `pg_cart_pending_offer_row_html()`, `pg_cart_pending_offers_html()`, `pg_cart_quick_add_option_html()`, `pg_cart_quick_add_html()` (the CSRF field passed in). The database and session reads stay in `_render_system_widget_shopping_cart()`, `_pg_render_pending_offers()` and `_pg_render_quick_add()`; the quick add's small script stays after the box.

## What does the canvas draw?
- Twins in `style_designer.src.js` (`_sdCartAppliedOffersHtml()`, `_sdCartPendingRecipientHtml()`, `_sdCartPendingRowHtml()`, `_sdCartPendingOffersHtml()`, `_sdCartQuickAddOptionHtml()`, `_sdCartQuickAddHtml()`), composed by `_sdCartSectionHtml(section, cfg)` with two sample offers, a sample tote bag gift with its recipient picker and two sample products.
- An empty section box in a shopping cart (`buildNodeEl()`, `_sdCartSectionSample()`) and the `applied_offers` content node draw that sample, inert, with a line saying the site fills it per cart, instead of the label box or the dashed placeholder.
- Quick add follows the widget's settings: no group chosen, the site prints nothing and the canvas keeps the label box; the heading is Quick Add, the text set, or none for an empty one.

## Does a running site change?
- No. The sandbox's Online Store cart was dumped before and after (quick add on an empty cart, then a cart with an automatic 10% offer and a gift waiting with its picker): every block and the page's text were byte-identical but the cart's reference code.
- `test_cart_blocks_markup_as_development_printed` keeps the branches the dump does not reach fixed in CI: development's quick add box with quantity, amount and recipient rows and an option out of stock, two offers, a gift with a decided recipient.

## How was it checked?
- `tests/cart_closed_blocks_test.php`: each twin byte for byte against its builder (one and two offers, a gift with a decided recipient, a picker, a picker with a new name; quick add options in and out of stock with quantity, amount and recipient rows), the sample's settings and the wiring.
- Sandbox: the Online Store template's cart page before and after on the canvas at 1440, 375 (the editor's phone width) and dark, and the site's cart with the offer applied and the gift chosen, under agent channel message 6108.
