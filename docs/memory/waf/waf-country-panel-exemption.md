---
name: waf-country-panel-exemption
description: Country restriction's panel exemption: the panel sign-in screens for anyone, every other software-directory script only with staff signed in; site sign-in pages not exempt, staff kept to the panel.
---

## Which panel requests does the country rule skip?
- With `waf_country_exempt_panel` on, every script under the software directory but the sign-in screens counts as panel only with a staff session (`waf_country_staff_signed_in()`, role < 3), `api.php` included (the store, cart and live chat call it too). The visitor scripts (`add_comment.php`, `custom_form.php`, `registration_entrance.php`, `express_order.php`, checkout…) follow the rule like site pages: before, a refused country posted to them (owner decision 2026-10-28, #2774, `tests/waf_country_panel_test.php`). The sign-in screens (`index.php`, `signin.php`, `mfa.php`, `passkey.php`, `sms_code.php`, `forgot_password.php`, `set_password.php`, `logout.php`) stay open to anyone so staff abroad can sign in. Site pages and files are decided in the router phase, without a session. Deferring that decision on the session cookie was deliberately not done: a cookie is no proof and the file path never reaches init. Staff abroad who need the site go on the Allowed IP list.
- The decision is the pure `waf_country_panel_exempt($script, $staff)`; `waf_country_is_panel_request()` asks the session only for a script that is not a sign-in screen. `router.php` and `get_file.php` are never the panel.

## Why are the site's sign-in pages not exempt?
- Login, forgot password and set password pages (legacy page types or designed widget pages) are site pages: do not exempt them. Staff from a refused country, while the panel is exempt, stay on the panel's built-in forms: `waf_country_keeps_to_panel()` → `pg_sign_in_kept_to_panel()` / `pg_sign_in_screen_url()` (`includes/fn/forms.php`), used by `get_login_screen()`, `get_forgot_password_screen()`, `forgot_password.php` (the reset link opens `set_password.php`, whose GET draws the form) and the after-sign-in home page redirect.
- A new place that sends someone from the panel to a site sign-in page or home page must ask it too. It answers false whenever it cannot tell (no database, Monitor mode, restriction off, allowed address, panel not exempt).
- Tests: `tests/waf_country_test.php`, `tests/waf_country_panel_test.php`.

Related: docs/memory/waf/waf-country-restriction.md
