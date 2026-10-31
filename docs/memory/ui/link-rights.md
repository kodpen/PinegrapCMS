---
name: link-rights
description: Names, orders and products link to their edit screen only when it admits the viewer (pg_can_open_user(), pg_can_open_contact(), USER_MANAGE_ECOMMERCE); Sessions rows drop refused actions
module: auth
---

## What is the rule?
- A name links to `edit_user.php` or `edit_contact.php` only when that screen would let the viewer in; otherwise it prints as plain text (`h()`), with no `<a>`. The rights themselves did not change.
- Symptom fixed: a commerce or ERP clerk (role 3 with `manage_ecommerce` / `manage_erp`) clicking a buyer, and a Manager clicking an administrator, a designer or their own account on Sessions, landed on "Access denied".

## What do the helpers check?
- `pg_can_open_user($user, $target_role)` (`includes/fn/auth.php`): role 0 always; role 1-2 only for a strictly lower role (not their own account); role 3 never. Mirrors `edit_user.php` (`'manager'` gate, then `$user['role'] >= $role` refused). The query of the screen reads `user_role` for it (SELECT only).
- `pg_can_open_contact($user, $contact_id, $contacts_access = null)`: no contact id -> false; role < 3 -> true with no query; role 3 -> `validate_contacts_access($user, true)` and `validate_contact_access()` (contact in one of their groups), as `edit_contact.php`. A list passes `$contacts_access` computed once per screen (`erp_accounts.php`). `$user = null` means the signed-in viewer (`USER_ROLE` short-circuit, else `validate_user()` once): used by `includes/erp/drawer.php` and `includes/erp/order_flow.php`, which get no user row.

## Which screens use them?
- `view_order.php` and `print_order.php` (User, Contact, affiliate; both read `user_role AS username_role` in the order query), `edit_gift_card.php` (From: user link, else contact link, else plain), `erp_accounts.php` (the contact icon shows unlinked with a hidden "Contact" label), ERP account drawer contact name, ERP order flow "Edit Contact" step link (left out), `view_sessions.php` (names, Manage button).
- Older guards of the same kind: `includes/fn/contact_list.php`, `includes/dashboard/widgets/widget_4.php`, `chat.php`.

## Which order and product links need the commerce right?
- `view_order.php`, `view_orders.php` and `edit_product.php` call `validate_ecommerce_access()`; a role-3 user with only `manage_ecommerce_reports` opens the reports and the Ecommerce Summary card. Without `USER_MANAGE_ECOMMERCE` these print the label as plain text (`h()`): Order Report detail order numbers (`$output_order_number`), Shipping Report day badge (a `span` with the badge look), Before/After/Total headings (`$output_orders_heading`) and product row headers, the card's Best Sellers rows (`'href' => ''`), and View Campaign's order reference (also escaped now). Pinned by `tests/report_commerce_link_rights_test.php`.

## What changed on Sessions besides the links?
- Rows the POST rule refuses (not own account, not administrator, role >= target role) are drawn without Lock/Unlock, Sign out, Test and Stop. Own sessions keep their actions though the name is not linked: the POST rule admits self, `edit_user.php` does not.

## How is it tested?
- `tests/panel_link_rights_test.php`: the role matrix of `pg_can_open_user()` and the paths of `pg_can_open_contact()` that need no database, and a source pin that every `edit_user.php` / `edit_contact.php` link in `view_order.php` sits next to its helper.

Related: docs/memory/core/permission-columns.md, docs/memory/ui/main-menu-entry-gates.md, docs/memory/ui/erp-link-rights.md
