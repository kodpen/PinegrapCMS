---
name: checkout-open-blocks
description: Order preview blocks opened (CB6d1): applied offers, gift cards and recurring payments as checkout lists (converter, starter), the installment choice and table drawn true; what stays closed
module: widgets_order_preview
---

## What did CB6d1 open?
- Step CB6d of `plans/designer-closed-blocks-steps.md`, cut by size; census [[canvas-closed-blocks-widgets]]. Shop steps before it: [[catalog-detail-open-blocks]], [[catalog-listing-open-blocks]], [[cart-open-blocks]].
- Tree: the order preview's `applied_offers`, `applied_gift_cards`, `recurring_payments` sections: lists of the `checkout` family ([[widget-lists-checkout]]).
- Settings only: `card_installments`, `installment_table` (the shopper's card and the gateway's plans).
- Closed: `order_recipients` is CB6d2 (nested items, product forms, gift cards, the custom shipping form's answers); `billing_form` and the custom shipping and billing forms stay as their form draws them.

## Which lists does an order preview offer?
- `pg_sw_kind_lists('order_preview')`: `checkout.offers`, `checkout.gift_cards` and the new `checkout.periods` (`__period_name`, `__period_subtotal`, `__period_tax`, `__period_total`; its only). Not `checkout.suggestions` nor `checkout.recipients`; the express order does not offer `checkout.periods`.
- Rows: `pg_op_list_rows($d)` from `get_order_preview()`'s data, published only when the tree drew a list; key `__ship_to_key` is filled `0`. Amounts are the classic figures as text (`_pg_op_price_text()`), so prices and totals are the checkout's own. A gift card the order takes nothing from gives no row, as in the section.

## How does a page get the trees?
- A saved page keeps its sections and prints what it printed. In the section's panel "Convert to design elements" (`data-sd-op-open`, `_sdOrderPreviewSectionToTree()`) makes the element hold the part; tag, classes and show condition stay.
- The part: `pg_op_part($section)` / `_sdOrderPreviewPart()`, the section's markup and words: the offers `ul.mb-0`; the gift cards `ul.list-group` with sentences "Gift Card {var:1}", "(Remaining Balance: {var:1})", "-{var:1}" and the Remove link; the periods table.
- The starter (`_buildOrderPreviewStarterTree`) and the server's layouts (`_pg_st_op_order()`) place the parts open. Not a default part (`SW_DEFAULT_PARTS`): those convert old trees on load.

## What does the canvas draw?
- An unopened section draws the twin of its builder (`_sdOrderPreviewSectionSample()`): `_sdOpOffersHtml()`, `_sdOpGiftCardsHtml()`, `_sdOpPeriodsHtml()` with a sample offer, gift card and monthly period.
- Installments follow `sdDesign.siteFlags.installments` (the iyzico limit, 0 when off): the box (`_sdInstallmentBoxHtml()`, twin of `pg_eo_installment_box_html()`) as it reads after a card is typed (`_sdInstallmentPlansHtml()`, the script's markup) and the table (`_sdInstallmentTableHtml()`, twin of `pg_payment_iyzico_installment_table_html()`), plans from the sample total; off: a line saying the site prints nothing.

## Does the site change?
- No: sections unchanged, the checkout posts, applies a gift card, chooses installments and charges as before.

## How was it checked?
- `tests/checkout_closed_blocks_test.php`: rows, the opened part drawn through the lists against the section's words and classes, the part twin, each builder twin byte for byte, the plans' markup against the script, the wiring.
