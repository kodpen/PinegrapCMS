---
name: layout-type-converter-entrance
description: Registration and membership entrance layout files into their widgets (LE10b, #11730): the login form cut and reported, the guest section held, the guest form after the widget's form, starting sites
module: layout_convert
---

## What does the map do?
- `_pg_lc_entrance_map()` in `includes/fn/layout_convert.php` ([[layout-type-converter]]): the sign-up form is the `registration` / `membership` widget's tree; its `<form>` tags go (echo `form:strip` on `$register_attributes`, `$attributes`, `$guest_attributes`), controls keep their classic names.
- `$strong_password_help` → section `password_rules`, `$captcha_question` → section `captcha` (membership: given empty, skipped), `PASSWORD_HINT` kept, `REMEMBER_ME` → flag `shows_remember_me` (new in both widgets), `h(MEMBER_ID_LABEL)` → `__member_id_label`.

## What happens to the login form?
- A second form never goes inside the widget ([[no-form-in-form]], [[layout-convert-widget-gap]]). Echo `cut:login_form` on `$login_system` marks it; `_pg_lc_cut()` removes the `<form>` holding the mark and every wrapper up that holds no other form (a column, a tab), so its heading or tab title goes too.
- Its forgot password link (`$forgot_password_url`, an `if` drop with `code`) and sign-in buttons (echo `drop:login_form`) go with it. All three are reported with code `login_form`: "the login form (…): the Login widget draws it".
- So a file page of these two types always drops: in a run it stays a System region ("Pages with a custom layout", reason "Its layout has pieces that have no design element."); the Region panel names the piece and "Convert to design elements" converts after the confirm. The designer adds the Login widget by hand; the widget's `login_link` section stays the way to the login page.

## And the guest block?
- `$guest` → section `guest` with `hold`: the block is read and kept inside the section's node (one element is bound itself, else a box holds it), its first submit button (button or submit input) bound to the action `continue_as_guest`. `$register_form->check_form_errors()` (the tab script) is skipped: the widget prints its errors.
- Server: `_pg_member_guest_apply()` drops the section and guest buttons unless the request has `allow_guest=true` (or edit mode); allowed, the button gets `type=submit` and `form="pg-guest-form-<widget id>"`, an empty section gets the site's button. `_pg_member_guest_form_html()` prints the guest form after the widget's form: `registration_entrance.php`, the token, `continue=true`, `send_to` (the query's, else the widget's), `allow_guest`. The widget's form carries the hidden `allow_guest` too.
- Fault fixed: a stock entrance page the run moved before this (a starter with no guest section) gave a guest no way on; the guest form now brings its own button when the tree has none. The starter has the section (a box with a "Continue as a Guest" button).
- Editor: the section in the registration widget's section list (kept children, an empty one sampled), the action in the button's Action list, the flag in "Show only when" for both widgets.

## Starting sites and tests?
- Page 126 on both sites (registration entrance, its own file): drops only the login piece (3 rows); before and after it stays a System region in a run. No starting-site membership entrance file. `tests/entrance_layout_test.php`.

Related: docs/memory/designer/layout-type-overview.md, docs/memory/designer/member-widgets-handlers-and-forms.md
