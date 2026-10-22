---
name: erp-account-prices
description: ERP account prices (4.94) in the line editor and, with erp_storefront_account_prices (10.59), in the store: the two layers, the campaign rule (erp_storefront_price), where the shop applies them.
---

## What are account prices? (`includes/erp/price_lists.php`, 2026.4.4 step 4.94)
- Terms agreed with one account, sales only: a price per product (`erp_account_prices.price`, replaces the list price) or a per-product discount (`discount_rate`), and an account-wide discount for everything else (`erp_accounts.discount_rate`). `erp_account_price_terms($account_id, $product_id)` gives them; kept in the catalogue's price basis. Edited on `erp_account_prices.php`.

## How does the line editor use them?
- `erp_product_for_line()` carries them as `account_terms`; `assets/js/erp_invoice_editor.js` applies them on sales only (invoices and quotes): an own price fills the unit price, a discount the discount box. The store's campaign is then left off: agreed terms stand in for it and never stack. The line stays editable.

## How does the store use them? (2026.4.10 step 10.59)
- Only while `config.erp_storefront_account_prices` is on (`ERP_STOREFRONT_ACCOUNT_PRICES`), for the signed-in member's own active customer account, not in a staff ghost session, never for a gift card or a donation.
- `pg_erp_member_prices()` (`includes/fn/ecommerce.php`) lists the products the terms make cheaper, read once per request. The catalogue price map (`get_discounted_product_prices()`) and the cart (`update_order_item_prices()`, `apply_offers_to_cart()`, `get_best_offer_id()`) weigh them.
- One rule: `erp_storefront_price($list, $terms, $offer_price)` (`includes/erp/storefront.php`): the lower of the account price and the campaign price wins, a tie leaves the campaign; with no terms the answer is exactly the list or campaign price, so a store with the setting off prices as before.
- A new path to a shop price goes through this function. Feeds and other listings that must not show one member's prices call `get_discounted_product_prices(false)`.
