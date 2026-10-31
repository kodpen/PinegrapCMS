---
name: affiliate-link-offer-code
description: When an affiliate link (?a=) puts the affiliate's code into the session as the special offer code: only when its group offer runs today (pg_special_offer_code_running(), #4163, PR #4266)
module: ecommerce
---

## What does an affiliate link do with the offer code?
- `get_page.php` reads `?a=`. Affiliate tracking (`affiliate_code`, commission) is set elsewhere and does not depend on the offer.
- With an affiliate group offer, approving an affiliate opens a key code equal to the affiliate code that points at the group offer's code. When no `?o=` and no session offer code is there, `get_page.php` writes the affiliate code into `$_SESSION['ecommerce']['special_offer_code']`, so the cart's Special Offer Code box comes filled.

## When is it written?
- Only when `pg_special_offer_code_running($_GET['a'])` is true: the code resolves through `get_offer_code_for_special_offer_code()` (offer code, or an enabled, unexpired key code) and at least one offer with that code runs today.
- "Runs" is `pg_offer_is_running()` (status `enabled`, `start_date <= today <= end_date`); `pg_offers_any_running()` checks the offers that share a code. The cart (`shopping_cart.php`) and express order (`express_order.php`) validation asks the same two functions, so the link and checkout cannot disagree.

## Why not change get_offer_code_for_special_offer_code()?
- It has many callers (cart, express order, cart_action, cart widget, `apply_offers_to_cart()` and others) that need the resolved code even when the offer is not running: the cart says "not available at this time" rather than "could not be found". It checks only the key code (enabled, expiry).

## What symptom did this fix?
- #4163: with the group offer disabled or out of its dates, every visitor coming through an affiliate link had the code put in the cart and the first Checkout failed with "The Special Offer Code you entered (…) is not available at this time"; the second press went through. The order still kept the affiliate code.
