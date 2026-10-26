---
name: pg-field-wrappers
description: pgChips, pgCombobox, pgDatePicker, pgDateTimeField (pg_fields.js): data-pg-* hooks, the posted field as store, own date parse, change event, v5 engine, screens
---

## Why do panel fields go through these wrappers?
- Bootstrap 6 Chips, Combobox and Datepicker replace tagin, select2 and the jQuery UI pickers at B9a-B9c (`plans/bootstrap-6-components.md`). A screen that marks its field with a hook and reads it through the wrapper keeps working when the engine in `pg_fields.js` changes; the screens stay as they are.

## How does a screen use them?
- Hook on the field: `data-pg-chips` (input; keep class `tagin`, the CSS hides it), `data-pg-combobox` (native `<select>`, no `select2` class), `data-pg-date`, `data-pg-datetime`, `data-pg-time`. liveform's `output_field()` prints any lower-case `data-pg-*` key (`'data-pg-date' => ''` gives a bare hook).
- Print `pg_fields_script()` (`includes/fn/core.php`) on the screen, next to `get_date_picker_format()` (the pickers read `datetimepicker_options`, time fields `timepicker_options` first). It prints the tag once per request. No inline `$(…).datepicker()` or `tagin()` call any more.
- Fields in the sent page are drawn on DOMContentLoaded; markup added later needs `pgFields.init(scope)` (not yet in `pgInitInjected()`: `backend.src.js` waits for a later slice).

## What do get() and set() exchange?
- ISO text: `'2026-10-26'`, `'17:00'`, `'2026-10-26 17:00'`; chips an array, a multiple combobox an array. Parse and format are our own from `date_picker_format` (day or month first, no leading zeros, `h:mm AM`), never `Date()` parsing. `pgDatePicker.parse/format`, `pgDateTimeField.parse/format/parseTime/formatTime` are exposed.
- The posted input or `<select>` is always the store, in the text the server reads today. `set()` writes it, fires one bubbling native `change` and redraws the widget (select2: `change.select2` only).
- Listen with `pgFields.onChange(el, fn)` or jQuery `.on('change')`: select2 announces a pick only through jQuery, so a bare `addEventListener('change')` misses it under v5.

## What is the engine today?
- `v5` only: tagin, select2 (theme bootstrap-5, `data-allow-clear`, `dropdownParent` from `pgUI.overlayHost()`), jQuery UI datepicker, datetimepicker and timepicker addon. A field whose library is not on the screen stays a plain field.
- `pg_fields.js` is served as written (no twin, no bundle). Tested under Node by `tests/pg_fields_test.php`.

## Which screens use them?
- Slice 1 (#1733): gift cards, shipping methods, ERP expense, receipt, return, transfer, invoice snooze, cheques, cheque, bank statement, export, account, invoice and waybill forms. The rest follow in later B6 slices (`plans/bootstrap-6-panel-steps.md`). Site renderers (order form, express order, cart, form views) stay on jQuery UI: never hook them.
