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

## What is left for the later parts?
- The cards still use label/value `.row` lines and the order lines their old boxes; the shipping card's date and tracking fields are not `form-field` yet.
