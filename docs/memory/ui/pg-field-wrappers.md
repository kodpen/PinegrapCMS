---
name: pg-field-wrappers
description: pgChips, pgCombobox, pgDatePicker, pgDateTimeField (pg_fields.js): data-pg-* hooks, the posted field as store, own date parse, change event, engines, screens
---

## Why do panel fields go through these wrappers?
- Bootstrap 6 Chips and Combobox replace tagin and select2 (B9a #2208, B9b #2243); dates run on the one control of `pg_datetime.js` (#2693). A hooked field read through the wrapper keeps working when the engine changes.

## How does a screen use them?
- Hook on the field: `data-pg-chips` (input; keep class `tagin`, the CSS hides it; `data-pg-separator=" "` for a class list, comma without it), `data-pg-combobox` (native `<select>`, `data-placeholder`; `data-pg-source="url"` searches the server: GET `q`, answer `{results:[{id,text}]}`, `data-pg-min-length`, `data-pg-free-text`; a stored value is an `<option selected>`), `data-pg-date` (`="plain"`: English names, Sunday first, for `view_order.php` ship and delivery dates), `data-pg-datetime`, `data-pg-time`. liveform's `output_field()` prints any lower-case `data-pg-*` key (`'data-pg-date' => ''` gives a bare hook).
- Print `pg_fields_script()` (`includes/fn/core.php`) on the screen, next to `get_date_picker_format()` (dates read the site order and `firstDay` from `datetimepicker_options`); once per request.
- Fields in the sent page are drawn on DOMContentLoaded; markup added later needs `pgFields.init(scope)`, which `pgInitInjected()` calls when the screen loaded the file. A settings card injected with `.html()` prints `pg_fields_script()` itself: a second load of the file keeps the first copy and draws the new fields (firewall card).

## What do get() and set() exchange?
- ISO text: `'2026-10-26'`, `'17:00'`, `'2026-10-26 17:00'`; chips an array, a multiple combobox an array. Parse and format are our own (`pg_datetime.js`) from `date_picker_format` (day or month first, no leading zeros, `h:mm AM`), never `Date()` parsing. `pgDatePicker.parse/format`, `pgDateTimeField.parse/format/parseTime/formatTime` are exposed.
- The posted input or `<select>` is always the store, in the text the server reads today. `set()` writes it, fires one bubbling native `change` and redraws the widget.
- A field that changes kind (calendar all-day: date and time to date) uses `pgFields.switchKind(el, kind)`: the old widget goes, one hook stays; `pgFields.destroy(el)` leaves a plain field with its value.
- Listen with `pgFields.onChange(el, fn)` or jQuery `.on('change')`: on a select2 (a `data-pg-source` field on a page without Bootstrap 6) a pick is announced only through jQuery, so a bare `addEventListener('change')` misses it there.

## What is the engine today?
- Chips: `v6`, Bootstrap 6 Chips (`pg-chips.md`). Combobox: `v6`, Bootstrap 6 Combobox (`pg-combobox.md`), server search for `data-pg-source` included (#2362). Date, date and time, time: `v6`, the calendar of `pg_datetime.js` on vanilla-calendar-pro and `<input type="time">` (`pg-datepicker.md`).
- `v5`: only select2 for a `data-pg-source` combobox on a page without Bootstrap 6 (theme bootstrap-5, `dropdownParent` from `pgUI.overlayHost()`). A field whose library is not on the screen stays a plain field.
- Both files are served as written (no twin, no bundle). Tested under Node by `tests/pg_fields_test.php`.

## Which screens use them?
- Slices 1-8 (#1733-#1820) moved every panel call site. `get_form_info()` hooks date fields only for `'backend'` (`preview_form.php` prints `$form_info['picker_scripts']`); site callers keep jQuery UI. The designer keyword box is redrawn by `pgChips.set()` after `_pgLoadPageIntoFields()` filled every field: its change reads all fields into the page. A field without its hook and `pg_fields_script()` stays plain. Form screens print the picker options once after the fields (date-time variant if any): hooked fields are drawn after load and share `datetimepicker_options`.
- Site renderers (order form, express order, cart, form views, page comments) stay on jQuery UI: never hook them before `plans/datetime-picker.md` moves them (#2693).
