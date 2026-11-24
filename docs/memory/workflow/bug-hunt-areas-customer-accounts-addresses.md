---
name: bug-hunt-areas-customer-accounts-addresses
description: Bug hunt area 37, the shop customer's account (2026-11-24): address book, order history ownership, guest orders, erasure; what was fixed, the issue opened, what read clean
---

## What was the area?
- The address book: `update_address_book.php`, `get_update_address_book.php`, `remove_recipient.php`, the address_book widget (`widgets_account.php`), the address book writes of `shipping_address_and_arrival.php` and `express_order.php`, the prefills (`get_shipping_address_and_arrival.php`, `get_express_order.php`, `_eo_prefill_shipping_fields()`, `_eo_render_saved_address_select()`, `_pg_sa_start_values()`).
- Order history: `order_history_view_order.php`, `order_history_retrieve_order.php`, `order_history_reorder.php`, `order_history_delete_order.php`, `get_view_order_screen_content.php`, the order_view widget, the account lists (`get_my_account.php`, the my account widget).
- Guest orders and accounts: `connect_user_to_order.php`, `retrieve_order.php`, `pg_order_auto_register()`, the completion's address book copy. Erasure: `pg_privacy_erase_tables()` for orders and ship_tos. Hunted in the PR titled "Bug hunt — customer accounts and addresses".

## What did it fix? (its PR lists each with file and line)
- A refused classic shipping step, or an express order update with a half-typed recipient, overwrote the saved address book entry with the broken address (`commerce/address-book-save.md`).
- A personal data erasure left the shipping address's district on the order's recipient (`core/personal-data-erasure.md`).

## Which issue did it open?
- #11435: an incomplete order taken over through its cart link (`?r=`) keeps the account of the person who built it: the buyer's billing address, e-mail and payment card show in that account's order history and order view, and not in the buyer's. Whose order it becomes is a product decision, so it waits for the architect.

## What was read clean?
- Every order history action (view, retrieve, reorder, delete) and the classic order view check `pg_order_view_owner_matches()`: a signed-in account and the order's own `user_id`; the order_view widget allows the owner, the session's just completed order or its receipt mail, and answers 404 for anything else.
- The account's order lists, the address book list and its edit form read by the signed-in account only; an edit or removal of another account's row changes nothing.
- A guest's cart is joined to the account at sign-in only while the order has no account or contact (`connect_user_to_order()`); auto-registration's link to the account with the billing e-mail and its address book copy are decided (`decisions-made-product-2.md`).
- Erasure keeps `orders.vat_id` and the order's amounts, numbers and payment references; billing fields, card fields, IP and the account and contact ids are replaced.

## What was left as it is?
- A renamed or removed address book entry stays in the session's ship to pick list until the session ends.
- The express order fills an address book address with the district of the order's recipient, which may be from an older address.

Related: docs/memory/workflow/bug-hunt.md
