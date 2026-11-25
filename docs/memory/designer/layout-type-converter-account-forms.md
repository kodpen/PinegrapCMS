---
name: layout-type-converter-account-forms
description: Login, forgot/set/change password, profile and e-mail preferences layout files into their widgets (LE10a, #11710): maps, new flags and tokens, differences, starting sites
module: layout_convert
---

## What do the maps do?
- `_pg_lc_password_map()` (login, forgot password, set password, change password) and `_pg_lc_account_form_map()` (my account profile, email preferences) in `includes/fn/layout_convert.php`, via `pg_layout_piece_map()` ([[layout-type-converter]]).
- The layout's `<form>` tags go (`forms: strip`): each widget draws its own form posting to the classic handler, so the controls keep their classic names. One record (`record`), but e-mail preferences has the widget's list.
- `$strong_password_help` → section `password_rules`. `PASSWORD_HINT`, `$my_account_url`, `$timezone` → kept: the widget drops the hint box (and its emptied wrapper) while the site asks for no hint, and a link bound to an empty address.

## Per type?
- Login: `REMEMBER_ME` → flag `shows_remember_me`, `$forgot_password_url` → `has_forgot_password_link` (new, set in `_render_system_widget_login_form()`: the box's label holds words of its own). `$google_signin` → section `google_signin`.
- Forgot password: `!$screen` kept; the hint screen (`elseif $screen == 'password_hint'`) is left out unreported: `forgot_password.php` no longer shows it.
- Set password: `h($email)` → `__email`. Change password: `!empty($password_not_set)` → `password_not_set`, else `has_password`.
- E-mail preferences: the mailing lists loop → the widget's list under `has_contact_groups` (`flag_list`); the row box `contact_group_^^__group_id^^` is named by the widget. New: row flag `group_has_description`, flag `shows_opt_in_sms`, tokens `__opt_in_label`, `__opt_in_sms_label`.

## Known differences
- E-mail preferences: the lists are always shown (the classic script showed them once the opt-in was ticked; `replace` removes the `display: none`).
- The profile template's `$account_security` block drops (reported): it is the account security widget's.

## Starting sites and tests?
- 12 file pages, 6 types on both sites (ids 895, 981, 980, 979, 975, 978): all convert with nothing dropped. `tests/account_forms_layout_test.php`.
- Address book waits for LE10c: its address type switch is not carried by the seed, and its help link needs a setting the widget has not.

Related: docs/memory/designer/layout-type-overview.md
