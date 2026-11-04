---
name: list-table-order-side-lists
description: Product attributes, cart quotes, returns, subscriptions and a contact's orders on the list table driver (TD10a): printed whole, what changes in place and what stays on the record's screen
module: commerce_list
---

## Which screens and how are they drawn?
- `view_product_attributes.php`, `view_cart_quotes.php`, `view_returns.php`, `view_subscriptions.php` (the list; one subscription's page keeps its plain tables), `view_orders_for_contact.php`: printed whole in browser mode (`pg_list_table_printed()`), each in `pg_list_table_printed_lists()` under its own name; sort codes are the column keys; a toolbar search box. Server filters stay (return status, subscription status/dates/customer, the contact).
- The first column opens the record (`data-pg-open`): the attribute's screen, the quote, the return, the subscription, the order. Old open/edit buttons are gone.
- Cart quotes: New link and Cancel stay submit buttons in an `actions` column inside the page's form. Returns: Approve Selected is the bar's button and posts `returns[]` (`ids_field`); `pg_returns_bulk_approve()` leaves rows not waiting for an answer as they are, so every row is selectable.

## What changes in place?
- Product attribute: name (not used by another attribute) and label (`pg_product_attribute_quick_update()`, `includes/fn/commerce_list.php`). The options are a list of their own: read-only here.
- A contact's orders: the status (complete/exported), through `view_orders.php`'s own quick edit answer, only for a user with the e-commerce right (the screen itself asks for the contacts right).
- Nothing on cart quotes, returns and subscriptions: they have no edit screen, only moves with their own checks and e-mails (cancel/new link, approve/receive/refund, pause/resume/cancel).

Related: docs/memory/ui/list-table-commerce-lists.md, docs/memory/ui/list-table-printed.md
