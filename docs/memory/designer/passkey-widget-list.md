---
name: passkey-widget-list
description: Passkeys in the designed account_security widget (PR #1843, #2279): the list, row tokens, flags, rename/remove, the add form with the e-mailed code, device rows
---

## How does the widget list passkeys?
- As a list (`widgets_lists.php`): a `loop_area` with `source: 'security.passkeys'` (family `security`, key `^^account_id^^`, filled with the member's id). Descriptor `pg_sw_list_security_sources()`, rows `pg_sw_list_passkey_rows()` (`includes/fn/widgets_account.php`), published after the static render. The device loop stays the main loop; the editor refuses the list inside it (`_sdListPlaceOk()`).
- Not a second widget-drawn loop: the list machinery gives the editor band, palette item ("Passkeys of the member"), Bind Data row tokens and the twin test for free.
- Row tokens: `__passkey_id`, `__passkey_name` ("Passkey" when unnamed), `__passkey_added`, `__passkey_last_used` ("Never"), `__passkey_is_synced` (1 or empty: a `show_if` not_empty condition, since row flags do not exist in lists).
- Widget flags (`eo_visible_if`) are applied to the whole tree before it is drawn, so they also work inside the list's rows (`passkey_can_change`, `passkey_asks_password`, `passkey_asks_code`).

## Which widget flags and tokens mirror the classic section?
- `passkeys_shown` (same rule as `pg_passkey_account_section()`: the site can use them, the account has some, or the host is another one), `has_passkeys`, `no_passkeys`, `passkey_can_change`, `passkey_can_add`, `passkey_needs_mfa`, `passkey_limit`, `passkey_unavailable`, `passkey_asks_password`, `passkey_asks_code` (`pg_passkey_reauth_fields()`).
- An operator signed in as the member: only `is_impersonated` + `^^__passkey_count^^`; no rows are published and every other flag is off.
- `^^__passkey_notice^^`: the refusal sentence of the site, the host or the limit.

## How do rename and remove post?
- The row forms are ordinary `account_security_form` forms. A button bound to `passkey_rename` / `passkey_remove` carries `pg_passkey_rename` / `pg_passkey_remove` = `^^__passkey_id^^` (one name, one value, as `revoke_device`); `account_security.php` turns that into the action and `passkey_id`. The rename field is `name`, its value `{{__passkey_name}}` (an attribute binding).

## How does adding work?
- A form bound to `passkey_add_form` gets `hidden`, `data-pg-passkey-add` and the CSRF field; it counts as a bound form. `pg_passkey_add_form_script()` (`includes/fn/passkeys.php`, appended once when such a form is drawn) unhides it when `PgPasskey.supported()`, else puts the "cannot create passkeys" sentence before it; on submit it calls `PgPasskey.register()` with `name`, `current_password`, `code` and reloads. Errors go to `[data-pg-passkey-message]` in the form, or a div it adds. Sentences: `pg_passkey_add_texts()`, shared with the classic section.

## How does an account without TOTP add one? (PR #2279)
- As on the classic page: password + a code e-mailed to the account (`email_code`). Flag `passkey_asks_email_code`; a button bound to `passkey_send_code` becomes `type="button" data-pg-passkey-send`, which the script wires to `PgPasskey.sendEmailCode()` (sentence into `[data-pg-passkey-sent]`, else the message). The first passkey's recovery codes replace the form (`showRecoveryCodes()`).
- Only a tree with a control named `email_code` offers it (`_pg_sec_tree_has_email_code()`); a tree saved before keeps `passkey_needs_mfa` (`pg_sw_passkey_add_state()`).
- New recovery codes: the `mfa_email_recovery` form (core/passkey-without-totp.md).
- Device rows: `__device_passkey` (the passkey that opened the session), flag `device_by_passkey`, `__passkey_id` filled so a `passkey_remove` button there removes it; the proof flags apply in device rows too.

## What was not verified?
- Unit tests only (`tests/widget_lists_passkeys_test.php`, the twin test); no browser or canvas run.

Related: docs/memory/core/passkey-account-section.md, docs/memory/designer/account-widget-forms.md, docs/memory/designer/widget-lists.md
