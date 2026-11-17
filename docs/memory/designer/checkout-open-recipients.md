---
name: checkout-open-recipients
description: Order preview recipients opened (CB6d2): three checkout lists, one row builder for the section and the lists, raw values and their flags, what stays the form's
module: widgets_order_preview
---

## What did CB6d2 open?
- The order preview's `order_recipients` section, step CB6d2 (#9966) of the closed-blocks opening ([[canvas-closed-blocks]]); CB6d1 opened the rest ([[checkout-open-blocks]]). The order preview is then settled: every section is open or drawn true, but the custom billing form's answers (`billing_form`), which stay as their form draws them.
- Three lists, the order preview's only: `checkout.ship_tos` (recipients with items, key `__ship_to_key` 0), `checkout.lines` (a recipient's items under its id; outside a recipients list the items that ship nowhere), `checkout.line_gift_cards` (an item's gift cards with a recipient, key `__line_key`).

## Where do the rows come from?
- `pg_op_recipient_rows($d)`: one row per recipient with items from `get_order_preview()`'s data, each with its lines and their gift cards. `pg_op_recipients_html($rows)` builds the section from it (byte for byte what development printed, `_pg_op_recipients_html()` its wrapper), `pg_op_list_rows()` files the list rows from it.
- Markup the classic page prints stays markup: `__ship_to_cost_html` (a discount is struck through), `__ship_to_form_html` (the custom shipping form's answers, as their form draws them), `__line_title_html` (the full description is the product's HTML), `__line_price_html`, `__line_amount_html`, `__line_stock_html`, `__line_forms_html` (product form answers). Raw keys in `pg_sw_raw_token_keys()` and `SW_ATTR_MARKUP`.
- A condition never reads a `*_html` value (`pg_sw_cond_token_allowed()`): flags `__ship_to_has_cost`, `__ship_to_has_form`, `__line_has_price`, `__line_has_stock_note`, `__line_has_forms` (1 or empty) carry it.
- The schedule is one text (`__line_recurring`, the classic words); a gift card line and the quantity line are sentences, one per case, chosen by a flag.

## How does a page get the tree?
- As CB6d1: "Convert to design elements" on the section's panel (`pg_op_part('order_recipients')` = `pg_op_recipients_part()`, twin `_sdOrderPreviewRecipientsPart()`); the starter and `_pg_st_op_order()` place it open; a saved page keeps its section.

## What does the canvas draw?
- An unopened section: `_sdOpRecipientsHtml(_sdOrderPreviewRecipientRows())`, the sample order's shirt with its product form shipped to the sample address and the mug in a group that ships nowhere, so the totals stay the sample order's. Opened: the recipient's card, its lines with the mug as the second sample row.
- A list in a `<ul>`/`<ol>` reaches half a rem left of it, padded as much: bullets clear the frame, words stay where the site puts them (architect, channel 6539).

## How was it checked?
- `tests/checkout_closed_blocks_test.php`: development's builder against the new one, the opened part through its three lists against the section's words and classes, the rows, the twins, for two recipients with a product form, gift cards, an event, a recurring item and a stock note.
