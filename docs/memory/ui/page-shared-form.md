---
name: page-shared-form
description: The page create and edit screens share includes/forms/page_form.php: the type picker and System Options card, and the save checks; what differs and what stays in the screens
---

## Where is the page form?
- `includes/forms/page_form.php` (gate `PG_FUNCTIONS_DIR`), loaded by `add_page.php` and `edit_page.php` together with `includes/fn/page_type_pane.php` (the type rights and the custom form check, shared with the designer's Page Type pane).
- Drawing: `pg_page_form_type_fields()` gives the type picker and the System Options card; `pg_page_form_new_page_properties()` the defaults of a new page.
- Saving: `pg_page_form_type_properties_from_post()`, `pg_page_form_save_type_links()`, and the helpers below. The save half moved here in #3024.

## Which save checks do both screens share?
- `pg_page_form_clean_name($name, $type)`: spaces and `&` become `_`; a catalog or catalog detail name with `/` gets the error. `pg_page_form_refuse($liveform, $errors, $url)` marks the errors and redirects back; it returns only when there are none.
- `pg_page_type_user_may_set($user, $type)`: create saves a type the user was not given as `standard`; edit keeps the stored type (`$may_save_page_type`).
- `pg_page_type_custom_form_errors($page_id, $check_settings)`: the form name another page holds, then `pg_cf_legacy_settings_errors()` with the live-page closure (written once here). Create passes page 0 and always checks the settings; edit checks the name always and the settings only when `$may_save_page_type` (`designer/custom-form-email-and-confirmation-fallbacks.md`).
- `pg_page_form_index_flags($sitemap, $_POST)`: noindex drops the page from the site map, nofollow only with noindex. `pg_page_form_noindex_sql($flags, pg_page_noindex_ready())` gives the INSERT `columns`/`values` and the UPDATE `set`, ints inline (`visitors/page-noindex-switch.md`).
- `pg_page_form_designer_redirect($type, $_POST, $current)`: `false`, or the `form_type` query for `view_fields.php`. Create passes no `$current` and goes on for every form that is on (a custom form page, the shipping/billing form, an express order form, shipping first). Edit passes the stored type and form flags and goes on only for a form this save switched on.

## What does a refused save bring back?
- What was typed, through the stored post (`page-form-refused-save.md`).

## What stays in the screens?
- Create: the folder access check, the blank-name and name-taken errors, the layout type, the INSERT, the per-type access trims and new form list view defaults, the regions, the redirect URL with `?from=control_panel`.
- Edit: the row load, the folder move check, the unknown-type fallback, the submitted-forms block on a type change, the site map type list, the UPDATE, the rename redirects, `send_to`.
- Every query of the screens stays in them; `db_q_pages_regions_test.php` (add_page 3) and `db_q_user_page_screens_test.php` (edit_page 29) count them. Tests: `tests/page_form_save_test.php`, `tests/page_type_pane_test.php` (two `pg_page_type_user_may_set(` in edit_page).
