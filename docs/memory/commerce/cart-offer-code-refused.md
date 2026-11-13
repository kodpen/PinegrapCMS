---
name: cart-offer-code-refused
description: Special Offer Code box of the classic Shopping Cart and Express Order: a mistyped or not running code is refused and the order keeps the code already applied, with its discount (#9069)
---

## What happens when the shopper types a code that is refused?

In `shopping_cart.php` and `express_order.php` a typed code that is not
found, or whose offers do not run today, marks the box with "The … that you
entered (…) could not be found" / "is not available at this time" and sets
`$special_offer_code_refused`. Then:
- `orders.special_offer_code` is not written, so the code already applied
  and its discount stay;
- the stored code is read back and put in the box, so the shopper sees which
  code still applies;
- the affiliate check does not run for the refused code.

An emptied box still clears the order's code; a valid code whose offer's
requirements are not met is kept with a notice, as before.

## Why?

Until #9069 both handlers emptied `$special_offer_code` on a refusal and
fell through to `UPDATE orders SET special_offer_code = ''`, so a typo took
off a working code and its discount (₺1.799,60 on the hunt's cart), with only
the "not found" error on screen. The designer cart (`cart_action.php`) never
wrote an invalid code, and the classic handlers now match it.
