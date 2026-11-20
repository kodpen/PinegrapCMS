---
name: bug-hunt-wishlist
description: Bug hunt of the wishlist (Favourites), visitor and member side (architect, agent channel message 10334, 2026-11-20): the two faults fixed, no issue opened, what was read clean
---

## What was the area?
- The Favourites list, visitor and member side: `wishlist.php`, `includes/fn/wishlist.php`, `assets/js/pg_wishlist.js`, `includes/fn/widgets_wishlist.php`. Given by the architect, agent channel message 10334 (2026-11-20). Not touched: `includes/fn/widgets.php`, `widgets_account.php`, `output.php`, `ecommerce.php`, `stock_notify.php`, `includes/api/`.

## What did it fix? (its PR lists each with file and line)
- A Favourites row with an offer showed the struck price but could not show the price before the discount the catalog cards show beside it (reference price rule, TR price label rules and EU Omnibus): the rows now carry `__reference_price` / `__reference_label`, read once for the list with `pg_price_reference_page()`, and the editor offers both.
- An element inside a saved product's card set to "Show only when" a list-wide flag (`is_signed_in`, `is_signed_out`, `has_items`, `no_items`) never showed: a row knew only its own flags. Rows now get the list's flags too.

## What was opened as an issue?
- None: no fault was found outside the area or needing a decision.

## What was read clean?
- Guest list into the account at sign-in: `INSERT IGNORE ... SELECT` then `DELETE`, the cookie cleared, skipped for staff signed in as a customer; a second merge finds nothing.
- The same product twice: the unique key and `INSERT IGNORE`; the cookie's expiry stays with the newest row.
- A disabled, binned or deleted product: not added (404), not listed or in the state, still removable; a permanent delete drops its rows.
- Writing to another list: the owner comes only from the session's account or the visitor's own cookie (stored as sha256); every write checks the session token with `hash_equals()`, then the rate limit.
- A shared list: there is none; the widget never reads another person's list, and a background render (search index, SEO score, preview, e-mail) shows the empty one.
- To the cart: the catalog card's `catalog_detail.php` fields, out of stock dropped by the catalog's rule, refusals back to the widget's messages.
- A member's agreed (wholesale) price: `get_discounted_product_prices()` weighs `pg_erp_member_prices()` as the catalog cards do; the page is personal anyway.
- The heart script: state and token read uncached, the session retry, busy hearts, variant changes, the back/forward cache.

Related: docs/memory/workflow/bug-hunt.md, docs/memory/designer/wishlist-widget.md, docs/memory/commerce/wishlist.md
