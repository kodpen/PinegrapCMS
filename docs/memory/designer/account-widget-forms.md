---
name: account-widget-forms
description: Designed account widgets: the login region's two states and per-placement ids, account_security's one form per block, links dropped in a grid, comments on a form detail page, one-screen widget kinds.
---

## How does the login region work?
- `login_region` (`includes/fn/widgets_account.php`) has two states, flags `is_signed_out` / `is_signed_in`; on the canvas the strip's chips switch which one is drawn.
- The same region can sit on a page twice (a header and an offcanvas menu): its ids get a per-placement suffix (`pg_sw_uniquify_row_ids()`) BEFORE the sign-in form is wrapped around it, so the CSRF field keeps its id. That form is `display:contents`: no box between a navbar and its items.
- Its Messages node is stamped with a form name nothing posts (`login_region`), so a header does not print the page's own form alerts (`member-widgets-rendering.md`).

## Why does account_security use one form per block?
- Each block is a `semantic` `form` bound with `_bindings.action = 'account_security_form'` (posts to `account_security.php` with the CSRF token and `return_to`); each button is bound to the action it takes (`pg_sw_account_security_actions()`: `logout_all`, `mfa_begin`, `revoke_device`…). Wired by `_pg_sec_apply_action_bindings()`.
- Not one wrapper with button name/value pairs: `pg_cf_validation_script()` validates the whole form, so a required code field would stop the other buttons, and Enter presses the first submit button. A tree with no bound form is still wrapped whole in one form, so its buttons post.

## What happens to a link bound to an empty address?
- `_pg_member_drop_empty_links()` drops a node whose `_bindings.href` token resolved to `''` (a feature that is off), and only that node. In a grid of link cards bind the cell itself (`a.col-*`), or an empty cell is left behind.

## Whose are the comments on a designed form detail page?
- The record's: `pg_sw_record_comment_context($page_id, $form_id)` finds the record the page's `form_item_view` widget shows (by id, else `?r=`), from that widget's own form only; who may see them follows the widget (`pg_sw_submitted_form_visible()`).

## Which widgets show one screen?
- `_SW_ONE_SCREEN` (`error_page`, `login_region`, `cart_link`) and `_SW_ACCOUNT_SINGLE` in `style_designer.js`: their loop area is hidden on the canvas and left out of the output. A new one-screen kind goes into the list. The error page itself: `error-page-widget.md`.
