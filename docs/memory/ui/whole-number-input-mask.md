---
name: whole-number-input-mask
description: Panel boxes of whole-number columns take Inputmask's integer alias, never decimal, so a Turkish 1.000 is a thousand
---

## Which mask does a whole-number box take?
- `data-inputmask-alias="integer"` (or `'data-inputmask-alias'=>'integer'` for `$liveform->output_field()`), with `inputmode="numeric"`. Inputmask's `integer` alias is `numeric` with no decimals, so a typed `.` or `,` is ignored and `1.000` becomes 1000.
- Never `decimal`: its decimal point is `.` and it turns a typed `,` into `.`, so a Turkish `1.000` (a thousand) stayed `1.000`, and the save (raw text into an int column, non-strict session) stored 1.
- Money boxes use the `currency` alias instead (`money-field-input-mask.md`).

## Which boxes were fixed?
- Ad region Transition Duration (`includes/forms/ad_region_form.php`; `1.000` was cut to `1.00` by maxlength 4 and saved 1 ms), form list view Maximum Number of Results and per page (`edit_form_list_view.php`), calendar event Initial and Remaining Spots (`includes/forms/calendar_event_form.php`; `1.000` saved one spot, so reservations closed after one).
- Left on `decimal`: slideshow interval, sort orders, widths and the recurrence count, which never reach a grouped thousand. The custom form membership, private and offer days on `page_form.php` are edited in the designer's JSON pane on visual-design pages, which is never masked.
- `tests/whole_number_mask_test.php` pins the five boxes. Not checked in a browser.

## How does a decimal box read a pasted comma?
- `backend.src.js` extends the `decimal` alias's `onBeforeMask` (it also runs on paste and autofill): with no thousands separator, the last `.` or `,` becomes the decimal point and earlier ones are dropped, as `pg_parse_commission_rate()` reads it. The stock alias split at `.` only, so a pasted `8,25` became 825 (#4230). A pasted `1,000` in a whole-number box is now 1.000: such boxes take `integer`.
