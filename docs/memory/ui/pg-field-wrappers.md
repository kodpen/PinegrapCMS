---
name: pg-field-wrappers
description: pgChips, pgCombobox, pgDatePicker, pgDateTimeField (pg_fields.js): data-pg-* hooks, the posted field as store, own date parse, change event, v5 engine, screens
---

## Why do panel fields go through these wrappers?
- Bootstrap 6 Chips, Combobox and Datepicker replace tagin, select2 and the jQuery UI pickers at B9a-B9c (`plans/bootstrap-6-components.md`). A screen that marks its field with a hook and reads it through the wrapper keeps working when the engine in `pg_fields.js` changes; the screens stay as they are.

## How does a screen use them?
- Hook on the field: `data-pg-chips` (input; keep class `tagin`, the CSS hides it; `data-pg-separator=" "` for a class list, comma without it), `data-pg-combobox` (native `<select>`, no `select2` class; `data-pg-source="url"` searches the server: GET `q`, answer `{results:[{id,text}]}`, with `data-pg-min-length`, `data-pg-free-text`, `data-placeholder`; a stored value is an `<option selected>`), `data-pg-date` (`="plain"`: only the date format, the picker's English names, for `view_order.php` ship and delivery dates), `data-pg-datetime`, `data-pg-time`. liveform's `output_field()` prints any lower-case `data-pg-*` key (`'data-pg-date' => ''` gives a bare hook).
- Print `pg_fields_script()` (`includes/fn/core.php`) on the screen, next to `get_date_picker_format()` (the pickers read `datetimepicker_options`, time fields `timepicker_options` first). It prints the tag once per request. No inline `$(…).datepicker()` or `tagin()` call any more.
- Fields in the sent page are drawn on DOMContentLoaded; markup added later needs `pgFields.init(scope)`, which `pgInitInjected()` calls when the screen loaded the file. A settings card injected with `.html()` prints `pg_fields_script()` itself: a second load of the file keeps the first copy and draws the new fields (firewall card).

## What do get() and set() exchange?
- ISO text: `'2026-10-26'`, `'17:00'`, `'2026-10-26 17:00'`; chips an array, a multiple combobox an array. Parse and format are our own from `date_picker_format` (day or month first, no leading zeros, `h:mm AM`), never `Date()` parsing. `pgDatePicker.parse/format`, `pgDateTimeField.parse/format/parseTime/formatTime` are exposed.
- The posted input or `<select>` is always the store, in the text the server reads today. `set()` writes it, fires one bubbling native `change` and redraws the widget (select2: `change.select2` only).
- A field that changes kind (calendar all-day: date and time to date) uses `pgFields.switchKind(el, kind)`: the old widget goes, one hook stays; `pgFields.destroy(el)` leaves a plain field with its value.
- Listen with `pgFields.onChange(el, fn)` or jQuery `.on('change')`: select2 announces a pick only through jQuery, so a bare `addEventListener('change')` misses it under v5.

## What is the engine today?
- `v5` only: tagin (`set()` drops the `.tagin-wrapper` and calls tagin again: tagin never hears a change), select2 (theme bootstrap-5, `data-allow-clear`, `dropdownParent` from `pgUI.overlayHost()`), jQuery UI datepicker, datetimepicker and timepicker addon. A field whose library is not on the screen stays a plain field.
- `pg_fields.js` is served as written (no twin, no bundle). Tested under Node by `tests/pg_fields_test.php`.

## Which screens use them?
- Slices 1-8 (#1733-#1820) moved every panel call site. `get_form_info()` hooks date fields only for `'backend'` (`preview_form.php` prints `$form_info['picker_scripts']`); site callers keep jQuery UI. The designer keyword box is redrawn by `pgChips.set()` after `_pgLoadPageIntoFields()` filled every field: its change reads all fields into the page. `backend.src.js` has no generic tagin, select2 or timepicker init: a field without its hook and `pg_fields_script()` stays plain. Form screens print the picker options once after the fields (date-time variant if any): hooked fields are drawn after load and share `datetimepicker_options`.
- Site renderers (order form, express order, cart, form views, page comments) stay on jQuery UI: never hook them.
