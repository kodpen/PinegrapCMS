---
name: site-native-date-inputs
description: Native date, datetime-local and time inputs on site pages get the date-and-time control by type, ISO kept; the server reads the ISO they post (S4a)
---

## Which inputs get the control?
- Every `<input type="date">`, `"datetime-local"` or `"time"` on a site page: system widgets (cart, express order, catalog product forms, shipping address), designer forms (`CF_PALETTE`) and inputs placed by hand in stored pages, old and new. The owner's symptom (2026-11-01): the browser's own picker still opened on designed pages.
- `pg_site_datetime_native_pattern()` (`includes/fn/site_datetime.php`) makes `pg_site_datetime_inject()` load the control for such a page; `type="datetime"`, `data-type="date"` or the words inside a value do not count.
- `pgFields.init()` in site mode (`hookNative()` in `pg_fields.js`) gives each one without a hook the hook of its type (`data-pg-date`, `-datetime`, `-time`) before drawing. A hooked input keeps its own hook; the panel hooks nothing by type.

## What do they post?
- ISO, as before: `2026-10-26`, `2026-10-26T17:05`, `17:05` (`pg-date-stores.md`); the store stays the native input, hidden behind the box.
- `validate_date()`, `validate_date_and_time()` and `prepare_form_data_for_input()` read ISO; `2026-10-26T17:05` (or with a space, seconds allowed) is a valid `date and time` and is stored as `2026-10-26 17:05`. Before S4a a designer or product form `date and time` field was refused (the value has no space) or stored as `0026-10-2026 …`.

## What value do the renderers put in them?
- ISO only, through `pg_native_date_value($value, $type)` (`includes/fn/forms.php`): a site-format default (`26/10/2026`, `26/10/2026 5:05 PM`, `5:05 PM`) or a stored `2026-10-26 17:05:00` becomes `2026-10-26`, `2026-10-26T17:05`, `17:05`; a date alone is midnight; text that is no date stays. Used by the designer form binding (`pg_cf_apply_field_bindings()`) and the classic layout converter (`_pg_lfc_controls()`) (S4b). Before, a field's site-format default was dropped by the browser.
- The editor writes no hook into trees: the site hooks every native input by type, stored trees included, so `style_designer.js` (palette, `f:date`, starters) needed no change. Magazine and news templates' `publish_date` (`datetime-local`) post through the S4a server path.

## What must a site script do around them?
- Write a value or toggle `disabled` on such an input, then call `pgFields.refresh(el)` (guard on `window.pgFields`): the box shows the input's state only when told. The express order's custom arrival date does this when its choice is picked.
- Never write site text into a native input: the browser drops it. The express order's submit used to rewrite every `input[type=date]` to `26/10/2026`, so the date posted empty; it now posts ISO. Its shipping methods request still sends the custom arrival date in the site's order (`isoToServerDate()`).

## Where is it tested?
- `tests/site_datetime_test.php` (`test_site_datetime_native_inputs`, `test_site_datetime_express_order_posts_iso`), `tests/pg_fields_test.php` (`dp_native`), `tests/forms_test.php` (`test_forms_date_and_time_iso`, `test_forms_native_date_value`), `tests/custom_form_url_prefill_test.php`, `tests/layout_form_convert_test.php`.

Related: docs/memory/ui/site-datetime-loader.md, docs/memory/ui/pg-date-stores.md, docs/memory/plans/datetime-picker-site-steps.md
