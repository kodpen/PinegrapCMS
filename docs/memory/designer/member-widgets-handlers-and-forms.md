---
name: member-widgets-handlers-and-forms
description: Membership widgets (login/forgot/registration/membership): legacy handlers, pg_member_register/activate, control naming, form wrap, validation script.
---

## Which handler do membership widgets post to?
- Widgets `login_form`, `forgot_password`, `registration`, `membership`. They post to the LEGACY handlers: login `<software>/index.php`, reset `forgot_password.php`, register `registration_entrance.php`, membership `membership_entrance.php` (both with `register=true`). Rate limit, lock, non-leaking notice and device limit live there; never open a second handler (like `member_action.php`).
- Widget-specific needs become parameters of that file: `return_to` = designed page to return to on error (via `pg_safe_redirect_path`), `send_to` = success target. They are separate questions; do not fold one into the other.
- Software-directory login screens go to the widget's page: `index.php` (panel; `validate_user()` sends here) and `forgot_password.php`, if no old page type exists, redirect with `send_to` via `pg_sw_screen_page_url()` to an openable page carrying the `login_form` / `forgot_password` widget. On the visual site the widget is the only thing making a page the login page; new code looking for `page_type = 'login'` uses the same fallback (`pg_sw_page_type_widget_url()` / `pg_sw_sign_in_url()`).

## Where is registration and activation logic?
- `pg_member_register($form, $opts)` and `pg_member_activate($form, $opts)` in `includes/fn/auth.php`; legacy screen and widget both call them. Validation and write order are a 1:1 port of legacy; a new rule there changes both screens, deliberately.
- `username` not posted -> derived from email (`field_in_session` separates "absent" from "empty"). Activation reads the address as `email_address` or `email`, whichever came.
- `$opts['contact_fields']` (column => value) is filtered against `pg_cf_contact_fields()`; a column name is NEVER read from POST. The form posts hidden `pg_widget_id`; `pg_member_widget_register_opts($sid, $form, $type)` resolves columns from that widget's own `tree_json` `_cf.contact_field`s and rejects another type's id.
- On activation `member_id` / `expiration_date` are the member list's data; the form cannot write them.

## How are controls and forms handled?
- A control is a field, its name is the contract: `input`/`select`/`textarea` inside the widget are the form's fields; `name` is legacy's (`email`, `password`, `remember_me`, `screen`). Server finds a control by name (`_pg_member_control_name`), fills it (`_pg_member_fill_control`; password never filled), drops a closed feature's control. Button is `<button type="submit">`; `<a href="#">` is not a button.
- Server wraps the form (`_pg_member_form_wrap`): `<form action=... method="post">` + `get_token_field()` + hidden fields. Designer form draws no form tag (same model as custom form).
- Membership form is also `.pg-cf-form`: same `novalidate` + `needs-validation` classes and `pg_cf_validation_script()`. The script is emitted once per page, waits for `DOMContentLoaded` and binds EVERY `.pg-cf-form` once (`data-pg-validated`); the old immediately-running version saw only its own form.

Related: docs/memory/designer/member-widgets-rendering.md, docs/memory/designer/member-widgets-tokens-and-fields.md, docs/memory/designer/member-email-asked-once.md (the address asked once)
