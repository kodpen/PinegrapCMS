---
name: order-preview-later-recipient-shipping
description: The classic Order Preview shows and counts the shipping of a recipient whose lines all start later, as submit_order.php charges it
module: widgets_order_preview
---

## Which recipients does the classic Order Preview count shipping for?
Every real recipient (`ship_to_id` not 0, shipping on) with a shipping cost,
also one whose lines are all recurring with a later start date: the block
opens through `pg_op_recipient_block_shown()` in `get_order_preview.php`.
`submit_order.php` charges `SUM(ship_tos.shipping_cost)` over every recipient
of the order, and the custom layout of the same file counts every complete
recipient, so the three agree.

## What happened before?
The block, and with it `$grand_shipping`, only opened when the recipient had
a line charged today. A lone shippable recurring product starting later
showed Total Due 0.00 and posted `total=0`; submit computed the shipping and
refused the order with "the total has changed", and the preview kept showing
0.00, so the customer could not check out (#7671).

## What does such a block show?
The ship-to header, the column headings and the shipping row, with no
product rows; the later lines stay in the recurring block below. A recipient
with only later lines and no shipping cost shows no block, as before.
