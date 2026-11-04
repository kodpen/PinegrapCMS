---
name: cart-quote-screen-layout
description: The new cart quote screen (add_cart_quote.php) on the product_builder pattern: which card holds what, the Summary that follows the boxes, the boxes' searches
---

## How is the screen laid out?
- The product_builder pattern (`edit-screen-wide-layout.md`, B11): the form column holds a Customer card (the contact box) and a Products and quantities card (10 product boxes, each with its quantity); the sticky status column holds Validity (Valid until, a `data-pg-date` field) and a Summary; Cancel and "Save and make the link" sit in the sticky save bar.
- There is no edit screen: a quote is changed only by Cancel or New link on `view_cart_quotes.php`, so the markup stays in the screen, not in `includes/forms/`.

## What does the Summary show?
- The chosen customer (or "Not chosen"), how many product boxes hold a product, their total quantity and the last day (or "No end"). PHP draws it from the liveform values, so it is right after a refusal; a script on the form (`jQuery(form).on('change input')`, which also hears a pick of the date picker and of a combobox) keeps it up to date. `add_cart_quote_summary_row()` draws one row.

## Where do the boxes search?
- Both are `data-pg-combobox` with the screen's own JSON search: `?request=contacts` (name, company, e-mail) and `?request=products` (enabled, no donations), 20 hits, 403 without the e-commerce right.

Related: docs/memory/commerce/cart-quotes.md
