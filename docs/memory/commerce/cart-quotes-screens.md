---
name: cart-quotes-screens
description: Cart quote list and new-quote form details: the products cell, New link confirmation, a deleted contact, quantity checks and redraw
module: cart_quotes
---

## How does the list keep its columns on screen?
- The products cell shows the first 3 as "name × quantity" and "and N more" (`pg_cart_quote_lines_summary()`), in a cell that wraps (inline `white-space: normal`, 12-24rem). One ten-product quote made the cell ~1400 px and pushed State, Valid until and Created off a desktop screen. The shared `table.chart` nowrap rule stays.

## Which actions ask first?
- New link and Cancel both ask (`data-confirm-content`): either kills the link the customer has. New link asked nothing before.

## What does a deleted contact look like?
- Deleting a contact leaves `cart_quotes.contact_id` (as for orders). `pg_cart_quote_panel_rows()` returns `contact_found`; a missing one reads "Deleted contact" with no link (it showed "#215" linking to a not-found page).

## How are quantities checked?
- `pg_cart_quote_quantity_valid()`: a whole number from 1 to `PG_CART_QUOTE_MAX_QUANTITY` (100000). `pg_cart_quote_lines_clean()` refuses a line or a repeated product's sum past it (`lines_invalid`, screen and API), never cuts it down (100001 was saved as 100000 without a word).
- The refusal says "Add at least one product." or "Each product needs a whole quantity from 1 to 100000."; the form marks the row's own quantity box (`is-invalid`, `max` attribute).
- Boxes start at 1 only on the first open; after a refusal they show what was sent. An emptied box came back as "1" and a second save stored a quantity nobody entered.

Related: docs/memory/commerce/cart-quotes.md
