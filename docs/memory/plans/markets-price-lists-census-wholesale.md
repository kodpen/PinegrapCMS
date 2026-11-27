---
name: markets-price-lists-census-wholesale
description: Markets and price lists MK1 census, wholesale: how an account's prices are stored and chosen at the store, and whether a market's price list could be laid the same way
---

## How are wholesale prices stored?
- `erp_account_prices` (`includes/migrations/2026.4.4.php:4106-4122`): `account_id`, `product_id`, `price` BIGINT (base minor units, 0 for none) or `discount_rate` DECIMAL(6,3); `erp_accounts.discount_rate` covers every product without a row (4124).
- `erp_accounts.price_account_id` (`2026.11.0.php:7729-7747`): the account buys at another account's rows and discount, one hop.
- A contact group gets offers (the group condition, quantity breaks), not stored prices (`commerce/wholesale.md`).
- Architect (2026-11-16, agent channel message 6553): no price list tables.

## How is a wholesale price chosen?
- `pg_erp_member_prices()` (`includes/fn/ecommerce.php:13208`): only with `ERP_STOREFRONT_ACCOUNT_PRICES`, for a signed-in member with an active customer account, never in a ghost session; gift cards and donations keep their price; the page is marked personal (`pg_page_cache_personal('member_price')`).
- `erp_storefront_price()` (`includes/erp/storefront.php:109`): the list price, the campaign's or the account's; the lowest wins, they never stack.
- `update_order_item_prices()` (`includes/fn/ecommerce.php:2101`) puts it into the cart; feeds, structured data and the API keep retail.

## Can a market's list be laid the same way?
- The shape fits: one row a list and product, a price or none, a pointer that lets one list serve many.
- The place does not:
- An account price is in base; a market's price is in the market's currency, so its row needs a currency, through its list.
- It is chosen by who is signed in; a market is chosen by the visitor's country before any sign-in, on pages the kept copy serves.
- It needs the ERP; a market must work on any shop.
- It only lowers a price (the lowest wins); a market's price may be above base converted.
- So a market's list needs a table of its own. The 2026-11-16 "no price list tables" was about wholesale; a market's table is a new question for the owner.
