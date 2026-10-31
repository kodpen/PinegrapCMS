---
name: erp-link-rights
description: ERP order, receipt, contact and user links and the Record a Receipt/Payment buttons show only when the target screen admits the clerk; plain text otherwise
module: auth
---

## Why do ERP links need their own check?
- ERP screens (`validate_erp_access()`) admit a role-3 clerk with `manage_erp` and the read-only accountant, who may lack the commerce, contacts or user rights the target screens ask for. A link they cannot open ended on "Access denied". The rights themselves did not change.

## Which order links are gated?
- `erp_order_link_html()` (`includes/erp/waybills.php`) links `view_order.php` only with `USER_MANAGE_ECOMMERCE` (true for every role below 3), else prints the number with `h()`, no `<a>`. Its last argument `$can_open` overrides the constant (tests run without it: default is no link).
- It draws every ERP order cell: Invoices and Delivery Notes lists, the invoice and delivery note screens, the ERP drawer (two rows). The File Manager's ERP section gates `order_url` the same way (`includes/explorer/erp.php`).

## Which receipt links are gated?
- `erp_receipt.php` and `add_erp_receipt.php` are cash-gated (`validate_erp_access($user, 'cash')`: role 3 needs `manage_erp_cash`), while accounts, the drawer and the audit trail admit any ERP clerk and the read-only accountant.
- `erp_can_open_receipt($can_open)` (`includes/erp/waybills.php`) answers `USER_MANAGE_ERP_CASH` (CLI default: no). `erp_receipt_link_html($id, $label, $class, $can_open)` asks it and links `erp_receipt.php` only then, else prints the label with `h()`. It draws the drawer's account statement, the account screen's statement and the audit trail's `receipt` lines. The invoice screen's allocation rows leave the receipt icon out without it (#4109).
- Every Record button (drawer: account and invoice; invoice screen's Settlement card) is drawn behind `$can_record_receipt = erp_can_record_receipt();` (`USER_MANAGE_ERP_CASH && !USER_ERP_READONLY`), worded by `erp_record_receipt_label($direction)`: "Record a Payment" on a purchase invoice, "Record a Receipt" otherwise. The invoice screen lacked the check and always said "Record a Receipt" until #4109. The server gates of both receipt screens are unchanged.

## Which contact card buttons are gated?
- `erp_account_form_contact_summary()` (`includes/erp/account_form.php`): "Contact" (`edit_contact.php`) only when `pg_can_open_contact(null, $id)`; "N order(s)" (`view_orders_for_contact.php`, same contacts gates) links with that answer, else a plain `fg-secondary` span; "User: name" (`edit_user.php`) links when `pg_can_open_user()` on `USER_ROLE` admits the target role, else plain text. The button row is left out when it is empty.
- `erp_contact_summary()` (`includes/erp/accounts.php`) reads `user_role` for that (SELECT only, default 3 without a user).

## How is it tested?
- `tests/erp_order_link_test.php`: link with the right, plain text without, the CLI default; the six call sites.
- `tests/erp_receipt_link_rights_test.php`: the receipt helper and its three call sites, the Record buttons of the drawer and the invoice screen, the invoice screen's receipt icon.
- `tests/erp_contact_card_link_rights_test.php`: source pins of the card's helpers and the `user_role` read.

Related: docs/memory/ui/link-rights.md, docs/memory/erp/erp-readonly-accountant-right.md
