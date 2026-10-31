---
name: erp-link-rights
description: ERP order, contact and user links (erp_order_link_html(), the account form's contact card) link only when the target screen admits the clerk; plain text otherwise
module: auth
---

## Why do ERP links need their own check?
- ERP screens (`validate_erp_access()`) admit a role-3 clerk with `manage_erp` and the read-only accountant, who may lack the commerce, contacts or user rights the target screens ask for. A link they cannot open ended on "Access denied". The rights themselves did not change.

## Which order links are gated?
- `erp_order_link_html()` (`includes/erp/waybills.php`) links `view_order.php` only with `USER_MANAGE_ECOMMERCE` (true for every role below 3), else prints the number with `h()`, no `<a>`. Its last argument `$can_open` overrides the constant (tests run without it: default is no link).
- It draws every ERP order cell: Invoices and Delivery Notes lists, the invoice and delivery note screens, the ERP drawer (two rows). The File Manager's ERP section gates `order_url` the same way (`includes/explorer/erp.php`).

## Which contact card buttons are gated?
- `erp_account_form_contact_summary()` (`includes/erp/account_form.php`): "Contact" (`edit_contact.php`) only when `pg_can_open_contact(null, $id)`; "N order(s)" (`view_orders_for_contact.php`, same contacts gates) links with that answer, else a plain `fg-secondary` span; "User: name" (`edit_user.php`) links when `pg_can_open_user()` on `USER_ROLE` admits the target role, else plain text. The button row is left out when it is empty.
- `erp_contact_summary()` (`includes/erp/accounts.php`) reads `user_role` for that (SELECT only, default 3 without a user).

## How is it tested?
- `tests/erp_order_link_test.php`: link with the right, plain text without, the CLI default; the six call sites.
- `tests/erp_contact_card_link_rights_test.php`: source pins of the card's helpers and the `user_role` read.

Related: docs/memory/ui/link-rights.md, docs/memory/erp/erp-readonly-accountant-right.md
