---
name: order-screen-wide-layout
description: view_order.php on the wide edit layout (B11 part 1): which card sits in which column, the sticky Order Information, the save bar column, pg_order_view_card_column()
module: order_view
---

## How is the order screen laid out?
- `view_order.php` follows `edit-screen-wide-layout.md`: a form column (`lg:col-8 2xl:col-9`), a status column (`lg:col-4 2xl:col-3`) and the save bar in its own `col-12 order-3 position-sticky` column (sticky on the column, as `product_builder.php`).
- The status column holds only the Order Information card (type, status, number, date, totals, codes) in a `position-sticky` box at `top:3.3rem`; it stays short enough for a laptop screen. Before, the screen was three equal columns with the totals in the first one and the save bar a plain sticky nav.
- The form column has two card columns, side by side only from `xl` (`col-12 md:col-6 lg:col-12 xl:col-6`): at 1024px half of the 8/12 column is too narrow for a label and its value. Left: Billing, Custom Billing, Customer, Payment. Right: one Shipping card per ship-to, Source, Applied Gift Cards, Affiliate, legal documents, Returns. The order lines (`.order-list-group`) span the form column under them.

## Why does a PHP helper drop the first card's margin?
- Every card is printed `card mt-5`. `pg_order_view_card_column($cards)` (`includes/fn/order_view.php`) joins a column's cards, skips empty ones and removes `mt-5` from the first, so the right column starts level with the left one whether the order has a ship-to or not.
- A rule in `backend.src.css` cannot: Bootstrap 6's `utilities` layer comes after `custom`, so `.mt-5` beats any panel rule. Tested in `tests/order_view_rows_test.php`.

## How is a label and its value drawn?
- Part 3: a line `d-flex justify-content-between gap-3`, the label `fg-secondary` without a colon, the value `text-end text-break`; `pg_order_view_row()` prints the same (also on `print_order.php`). Before, a `.row` of two `.col` halves wrapped a country or an e-mail inside half the card. The address lines (`pg_order_view_row(..., true)`, Address 1, the ship-to Address) keep their own column layout, also without a colon.

## What is left for the later parts?
- The order lines (`.order-list-group`) keep their old boxes.

## How are the shipping card's fields drawn?
- Part 2: Ship Date and Delivery Date are labelled `form-field` boxes side by side from `sm` (`<label for>` naming each date field; before, the captions named no field). A cancelled order shows them as text under a plain caption, a dash when empty. Tracking Numbers (the parcel list and the chips field) and Shipping Labels each sit in a `form-field` with a `form-label` caption. Pinned by `tests/field_caption_links_test.php`.
