---
name: contact-orders-list
description: All Orders for Contact (view_orders_for_contact.php): open carts left out, a Status column with the orders list's badge, paid_at probed
---

## What does the list show?
- `view_orders_for_contact.php` (Edit Contact › View Orders) lists the contact's rows of `orders` whose `status` is not `incomplete`: an open cart has no number and a zero total, and the contacts list's My Customers view leaves it out too, so the two screens agreed on nothing (#5334). The count and the page share the one `$where`.
- A Status column (sortable, code `status`) draws `pg_order_list_status_html($row, $paid_at_column_exists)`, the badge of the orders list: cancelled, complete, exported, Awaiting Payment and the Paraşüt mark. The row reads `status`, `payment_method`, `parasut_exported`, `parasut_invoice_id` and, when `pg_schema_has('orders', 'paid_at')`, `paid_at`.
- Test: `tests/view_orders_for_contact_test.php`.

Related: docs/memory/ui/contact-shared-form.md
