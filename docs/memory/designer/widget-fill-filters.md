---
name: widget-fill-filters
description: A bound widget value's format (_bindFormats): text filters or a date format, how the suffix is read, which fills read it, escaping, Turkish case, what a condition compares, typed dates.
---

## What can a bound value's format be?
- `props._bindFormats[prop]`, written by `_apply_bindings()` after the token as `^^token^^%%format%%` (only `[A-Za-z0-9 /.:,-]` survive).
- Text filters (`pg_sw_fill_filters()`, `includes/fn/widgets_fill.php`): `upper`, `lower`, `capitalize`, `number:N` (decimals 0-6), `truncate:N` (characters 1-1000), `yesno`, `nl2br`, `url`. Words are space separated and run left to right: `truncate:120 nl2br`.
- A date format (`d.m.Y`, `j F Y, H:i`, `relative`) for a date value.
- The panel's Format row (`_sdFormatRows()`) writes one filter; a chain written by hand shows as "Written: …" and is kept. `url` is server only (addresses).

## How is a filter chain told from a date format?
- `pg_sw_fill_parse()`: every word is a known filter, with `:N` exactly when the filter takes a number. One other word makes the whole suffix a date format, as before filters existed, so stored trees keep their meaning. The editor's `_sdParseFilter()` reads one word the same way (`tests/widget_fill_test.php` runs both).

## Which fills read it?
- `pg_sw_apply_tokens()` (form views, my_account, the comment date): a filter works on the field's output (a date in its default format, a counter as a number) and on `$extra` values; a date format on a date field as before.
- `_pg_sw_fill_tokens()` and `_pg_member_apply_tokens()` (custom form too): a template holding `^^%%` goes through `pg_sw_fill_typed()`, one pass that never rescans what it wrote; one without fills byte for byte as before (strtr / str_replace loop).
- Not yet (part 2 of W14): catalog, catalog detail, cart, express order, order view. Their `str_replace()` fills would print the suffix, so `_SD_FILTER_KINDS` (an allow-list) leaves them, and any kind added later, without the Format row.

## Why decode, filter, then escape once?
- Values arrive escaped. `pg_sw_fill_value()` decodes, filters plain text, escapes once (`pg_sw_fill_apply()`), so `A &amp; B` gives `A &amp; B`, never `&amp;amp;`, and truncation never cuts an entity.
- A value holding markup (raw keys, a badge, a link list, a relative date's tooltip, rich text) comes back unfiltered. The `<br />` line breaks `prepare_form_data_for_output()` adds are read as line breaks and written back.
- Carets come out as `&#94;`, so a filtered value never forms a token the sweep would eat (comments and list filters protect visitor text so).

## Why does case mapping know Turkish?
- mbstring maps `i` to `I` and `I` to `i`. In `tr` and `az` (`pg_sw_fill_lang()`: page language, else source language) the pairs are i/İ and ı/I: `istanbul` gives `İSTANBUL`, `IRMAK` gives `ırmak` (`pg_sw_fill_case()`).

## What does a condition compare?
- The plain value: a `show_if` carrier holds a bare `^^token^^` (no suffix), so `truncate:2` on `Ankara` shows `An…` while `eq Ankara` still passes ([[widget-conditions]]).

## Which date tokens are typed?
- `_pg_sw_calendar_event_times()`: `__event_date`, `__event_start_date`, `__event_end_date` carry `Y-m-d`; a date format formats that date. `SW_DATE_FIELD_TOKENS` lists them, so the calendar widgets draw a Date format row (the form views and my_account draw theirs in the binding rows).
- `date()` month and day names (F, M, l, D) are English, as in the form views' date formats.
