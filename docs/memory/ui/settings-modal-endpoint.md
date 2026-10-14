---
name: settings-modal-endpoint
description: settings_pane.php rules: always JSON, pg_error_throws, access gate, manual CSRF, remove_form, re-read after save, Safe Mode scheme change.
---

## What must the endpoint (`settings_pane.php`) guarantee?
- The answer is always JSON, errors too. `pg_error_throws(true)` (`includes/fn/core.php`) makes `output_error()` throw an exception (twin of the `pg_seo_rendering()` flag); the endpoint calls card generation and save inside `try`/`catch`. The message passes through `pg_settings_pane_complaint()`, because `output_error()` writes for a screen and may carry a "go back" link.
- Gate at the endpoint: `validate_user()` + `validate_area_access($user, 'manager')`. A button that is not drawn is not an access check.
- CSRF is compared by hand (`hash_equals`): `validate_token_field()` prints a screen. The token is renewed in every pane answer, because another tab can spend it while the dialog stays open. 403 carries `expired: true` and the browser reloads the page; the screen behind the dialog is stale at that point anyway.
- `liveform` form is dropped in both branches (`remove_form()`). `get_warnings()` / `output_notices()` read from the session and do not delete; unless dropped after the pane is drawn, the same notice returns on every open.
- The form carries `data-pg-no-curtain`: `pg_preloader_markup()`'s `submit` listener runs on the document in the capture phase, before the form cancels its submit; Enter in a text field would drop the full-screen curtain over a page that goes nowhere.

## What happens after a save?
- The pane is RE-READ after save. Save normalizes (a list is joined with commas, a zero limit rises to one); the operator must see what is stored, not what they typed.
- A save that turns Safe Mode on changes the URL scheme: the answer carries the address in the new scheme (`reload`) and the browser moves there; any request to the old scheme would be blocked as mixed content.
- Safe Mode lives in the Firewall pane (HTTPS and Security Headers card). `firewall.save.php` produces `$url_scheme`, `fragment.php` reads it. `general.save.php` must NOT write `url_scheme`: if it did, saving General would turn Safe Mode off.

Related: docs/memory/ui/settings-invalid-vs-error.md
