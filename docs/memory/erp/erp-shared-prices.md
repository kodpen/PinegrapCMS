---
name: erp-shared-prices
description: An ERP account using another account's prices (price_account_id, 11.0.235): one hop checked at the save, where the pointer is followed, retail in public
---

## What is a shared price?
- `erp_accounts.price_account_id` names the account whose rows (`erp_account_prices`) and account-wide discount another account buys at instead of its own; 0 uses its own, as every account did before. A price list is a template account's prices, edited on the screen that exists.
- Only the prices and the discount follow the pointer; payment terms, credit limit and everything else stay the buyer's own. The pointing account's own `discount_rate` and rows stay stored, unused while it points, back in use when it lets go.
- `erp_shared_prices_ready()` asks for the column; without it every account uses its own prices.

## How far does the pointer go?
- One hop. `erp_account_price_pointer_save()` refuses (`erp_account_price_pointer_refusal()`, pure): its own id, an account that does not exist, an account that points on itself, and any pointer while other accounts use this one's prices. Letting go (0) is always allowed.
- `erp_account_price_source()` follows exactly one pointer, also when a row got past the save's check: a pointer at a pointing account gives that account's own prices, a pointer at itself reads as none. A pointer at an account that no longer exists gives no terms: the list price, not the buyer's own rows.
- Accounts have no delete (set to passive instead). A delete path asks `erp_account_delete_refusal()` with `count(erp_account_price_users($id))`: accounts that use its prices let go first. A passive template account's prices are still followed: only the buyer's account passes the store's gate (active, customer or both); the pointed-at account is read for its rows and discount alone.
- Architect (2026-11-17, agent channel message 7602): the column, its index, and both choices above.

## Where is the pointer followed?
- `erp_account_price_terms()`: the line editor (`erp_product_for_line()`) and the store's per-product terms read the source account's rows and discount.
- `pg_erp_member_prices()` takes the products to weigh from `erp_account_storefront_scope()`, so the cart, line prices, offers and product page get the shared price with no change of their own. The recorded rule against a campaign stands (`erp_storefront_price()`, `erp-account-prices.md`).

## Where does retail stand?
- Feeds call `get_discounted_product_prices(false)`, the structured data reads `products.price`, the API's product fields are the list price.
- `pg_erp_member_prices()` marks the page personal (`pg_page_cache_personal('member_price')`) when it returns any price, so the page cache never keeps a page drawn with one member's price (signed-in pages already bypass it).
