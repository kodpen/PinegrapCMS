---
name: layout-type-converter-account-address
description: My account and update address book layout files into their widgets (LE10c): address type flag, help page link, value conditions, orders list, drops, new converter rules
module: layout_convert
---

## What does the address book map do?
- `_pg_lc_address_book_map()` in `includes/fn/layout_convert.php` ([[layout-type-converter]]): the recipient form of the `address_book` widget; `<form>` tags go, controls keep their classic names, no list (`record`).
- `$address_type` → flag `has_address_type` (new in the widget: its `cfg.address_type`). The seed carries the type row's switch into `cfg.address_type` (`pg_layout_widget_seed()`, table `update_address_book_pages` in `_pg_widget_type_facts()`).
- "What is this?" (`$address_type_url`): a link bound to the page the row's `address_type_page_id` names (`_bindings.href = '__page_link'`, `_bindParams.href = {page_id, params: []}`, [[page-link-by-id]]). With no page it is left out unreported (`pg_layout_convert_state()` gives `address_type_page_id`). Architect rule: [[layout-convert-widget-gap]].
- Widget: `pg_sw_page_link_bindings()` + `pg_sw_page_link_resolve()` fill a page link's address; a page gone or binned drops the link (`_pg_member_drop_empty_links()`).

## What does the my account map do?
- `_pg_lc_my_account_map()`: the member's lines become the widget's account tokens (`username`, `email_address`, `first_name`, `last_name`, `__member_*`, `member_id`, new `__member_id_label`), each shown under a value condition (`show_if`), not a flag: the widget reads its values after its flags.
- Links: `__profile_url`, `__email_preferences_url`, `__change_password_url`, `__logout_url`, `__address_book_url` (the widget drops a link with nowhere to go). Complete orders → the `account.orders` list (`source:`) under `has_orders`; the View button shows when `__order_url` is not `#`; the Reorder button goes (`unlink:`).
- Stock template only: the store credit block → sections `store_credit` and `store_credit_convert` under `has_store_credit`; the personal data block under `__privacy_request_url`.

## What drops (reported)?
- Business address lines 1 and 2, state, zip and fax (not among the owner's account values, [[widget-entity-fields-list]]), the start page link, the affiliate block, the incomplete orders, the saved recipients (lists of their own), a theme's own PHP (`check_view_access()`), the reorder address; the template's Google sign-in notice.
- So a my account file page always drops: in a run it stays a System region; the Region panel lists the pieces and "Convert to design elements" converts after the confirm.

## Which converter rules did it add?
- Map `when` => flag => `{tokens, op, value}`: a map flag that is no widget flag becomes `show_if` (`show_if_2`, `show_if_3` joined `any`) (`_pg_lc_when()`).
- Map `page_links` => token => state key (`_pg_lc_page_link()`); echo `unlink:<code>`: the link holding the value goes, reported (`_pg_lc_unlink()`).
- An "otherwise" branch inside a line of text is split into its own span like an "if" (`_pg_lc_text_flags()` reads the marker's extra attribute).

## Starting sites and tests?
- Page 976 (address book, both sites; row asks the type, names no page): converts with nothing dropped, the type radios under `has_address_type`. Page 971 (my account, both sites): 14 pieces drop. `tests/account_address_layout_test.php`.

Related: docs/memory/designer/layout-type-overview.md, docs/memory/designer/layout-type-converter-account-forms.md
