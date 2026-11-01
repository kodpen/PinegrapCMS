---
name: account-page-type-pick
description: Which designed page answers a legacy account page type (password reset link, Change Password, order links, sign-in): a page the visitor can open, the home page's design first (#4451)
module: widgets_account
---

## Which page does `pg_sw_page_type_widget_url()` return?
- Among the pages carrying the widget that stands in for the type (`pg_sw_widget_pages()`, title order; for `'view order'` without the receipt e-mail pages), `pg_sw_pick_viewable_page()` (`includes/fn/widgets.php`) takes the first page whose folder the visitor can open, a page of the home page's design (`pg_sw_home_style_id()`) before the others. None: `''`, and the callers fall back as before (legacy page type, `set_password.php`).
- "Can open": types a signed-out visitor uses (`pg_sw_page_type_for_signed_out()`: login, forgot password, set password, registration entrance) need a `public` folder; the rest use `pg_sw_visitor_may_open_folder()` (`check_view_access($folder, true)`, or public/guest/registration without a visitor), the check the product detail page picker uses (#4453).
- A Manager passes every folder check, so the home page's design is what keeps their links off the drafts.

## Why?
- On dev the live design (Kafe) sat beside two designs in the private Drafts folder with same-titled account pages that sorted first by name. The password reset e-mail linked to `/parola-belirle` (a draft): the signed-out visitor was sent to `/giris` and could not reset; My Account's Change Password and order links went to drafts too (#4451).
- Callers: `get_page_type_url()` (forgot_password.php's reset link, get_my_account.php, auth.php, change_password.php), `_pg_acct_page_url()`, logout.php.

## How is it tested?
`tests/visitor_widget_page_pick_test.php`: the picker's order (published design first, drafts skipped), the signed-out types, and that the account and error page lookups use it (read from source; they need a database).
