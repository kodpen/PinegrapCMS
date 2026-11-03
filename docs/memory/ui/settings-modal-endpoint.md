---
name: settings-modal-endpoint
description: settings_pane.php rules: always JSON, pg_error_throws, access gate, manual CSRF, remove_form, re-read after save, reload on a language change, Safe Mode scheme change.
---

## What must the endpoint (`settings_pane.php`) guarantee?
- Past the gate the answer is always JSON, errors too; the gate itself (`validate_user()`, `validate_area_access()`) runs before `pg_error_throws(true)` and refuses with the HTML error page (the dialog is drawn only for roles 0-2). `pg_error_throws(true)` (`includes/fn/core.php`) makes `output_error()` throw an exception (twin of the `pg_seo_rendering()` flag); the endpoint calls card generation and save inside `try`/`catch`. The message passes through `pg_settings_pane_complaint()`, because `output_error()` writes for a screen and may carry a "go back" link.
- Gate at the endpoint: `validate_user()` + `validate_area_access($user, 'manager')`. A button that is not drawn is not an access check.
- CSRF is compared by hand (`hash_equals`): `validate_token_field()` prints a screen. The token is renewed in every pane answer, because another tab can spend it while the dialog stays open. 403 carries `expired: true` and the browser reloads the page; the screen behind the dialog is stale at that point anyway.
- Both branches hand over `notices`, `errors` and `warnings` (`get_warning_messages()`: a refused Paraşüt code, a maintenance allow list open to all, a product feed not built) before the drop; the dialog shows warnings in `theme-warning` above the green "saved". Before #5020 they were dropped unseen.
- `liveform` form is dropped in both branches (`remove_form()`). `get_warnings()` / `output_notices()` read from the session and do not delete; unless dropped after the pane is drawn, the same notice returns on every open.
- The form carries `data-pg-no-curtain`: `pg_preloader_markup()`'s `submit` listener runs on the document in the capture phase, before the form cancels its submit; Enter in a text field would drop the full-screen curtain over a page that goes nowhere.

## What happens after a save?
- The pane is RE-READ after save. Save normalizes (a list is joined with commas, a zero limit rises to one); the operator must see what is stored, not what they typed.
- A save that turns Safe Mode on changes the URL scheme: the answer carries the address in the new scheme (`reload`) and the browser moves there; any request to the old scheme would be blocked as mixed content.
- A save that changes the panel language (`pg_software_language_changed()`, `includes/fn/lang.php`: `lang(array('info' => true))` against `pg_software_language_pick()` of the enforced, the stored and the default language; with an enforced language nothing changes) answers `relocalize: true` without re-reading the pane. The dialog sets `#settings/<pane>` with `history.replaceState` (a plain hash set fires `hashchange` and loads the pane first) and reloads; `fromHash()` opens the pane again and its read hands over the "saved" notice, added in the new language (`lang(array('string' => …, 'language' => <code>))`) and kept in the `settings` liveform until then. Before, only the re-read pane changed language; the dialog frame, the notice and the panel behind stayed in the old one until the next screen. Test: `tests/settings_dialog_relocalize_test.php`.
- The acrylic effect is `body.advanced-visuals`, drawn with the page (`output.php`). The Theme card carries the stored value (`data-pg-advanced-visuals`) and its script toggles the class on every pane read, so a save shows on the dialog and the menus at once. Custom CSS and feature switches still show only on the next screen.
- Safe Mode lives in the Firewall pane (HTTPS and Security Headers card). `firewall.save.php` produces `$url_scheme`, `fragment.php` reads it. `general.save.php` must NOT write `url_scheme`: if it did, saving General would turn Safe Mode off.

Related: docs/memory/ui/settings-invalid-vs-error.md
