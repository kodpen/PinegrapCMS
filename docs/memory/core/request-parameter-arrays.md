---
name: request-parameter-arrays
description: How screens must treat a request parameter that arrives as an array (?id[]=x), missing records, and null passed to escaping helpers
---

## What if a single-value parameter arrives as an array?
- It counts as "not given": the HTML is unchanged and nothing answers 500. Check with `is_string()` (GET) before use; never cast an array to string or int.
- `send_to` and `from` are cleaned once, centrally, in `init.php` via `pg_request_single_values()` (`includes/fn/core.php`); do not re-check them per screen.
- ERP: every POST form calls `pg_request_single_values()` for its single-value fields.
- Short link screens (wizard, classic add/edit): see `commerce/short-link-catalog-bin.md`.
- Settings saves refuse instead (not given would blank the setting): see docs/memory/ui/settings-invalid-vs-error.md.

## What about the entries of a list a form posts?
- Same rule, one level down: an entry that is itself an array (`choice[email][]=x`, `allocate[5][]=x`, `counted[29][]=x`, `map[0][]=x`, `lines[0][description][]=x`) counts as not given. Check `is_scalar($entry)` before a cast; read a row of fields (`lines[n]`, `categories[id]`) through `pg_request_row_values($row)` (`includes/fn/core.php`), which drops non-scalar fields and keeps numbers from stored rows and JSON bodies.
- Why (PR #476): `(string)` logged "Array to string conversion" and saved "Array" as an invoice/delivery-note line description or a category name; an array count was refused as "not a whole number". Now the description is missing ("Line 1 needs a description."), the category asks for a name, the count line keeps its figure.
- `tests/erp_request_values_test.php` fails when an ERP screen casts or reads a posted list entry without the check.
- The reverse: a list sent as one value (`quantity=5` where the cart and express order post `quantity[<item>]`, `donations[<item>]`) counts as not given; check `is_array()` first. Before (PR #486): the cart answered 500, a long value gave a donation one character as its amount.

## What about request values kept in the session?
- Write them only when they are text (and, for a fixed set like a calendar view, one of the set); otherwise keep the old session value. Readers also ignore a non-text session value, since an older version may have kept an array.
- Calendars: `calendars.php` (panel, #417) and `get_calendar_view_screen_content.php` (site calendar pages) do this for calendar_id, view and date; `get_calendar()` drops a date that is not text. Before (PR #471): one `?date[]=x` or `?view=x` link made every calendar in that session warn ("Array to string conversion"); an unknown view left it empty.

## Report screens (order, visitor, shipping)?
- Order and visitor report forms call `pg_request_single_values(array_keys($_GET + $_POST))` in the POST branch before liveform copies `$_REQUEST` into the session (no field is a list). Before (PR #478): any array field answered 500, `last_filter_number[]` looped until the 30 s limit, the visitor report then showed "Query failed" for the session.
- Date changer parts use `is_scalar()`: `(int)` of an array is 1, so `?start_year[]=x` stuck the range at year 0001.
- The shipping report stores only a valid Y-m-d date and a known status; a bad one stayed in the session ("The start date is invalid" or warnings on every visit).
- `view_visitor.php` checks `visitor_report_id` with `is_scalar()` (array: 500).

## What if a record screen gets no id or an unknown id?
- Answer "Kayıt bulunamadı" (`lang('Record not found.')`) with 404. Never fall back to record 1.
- Token-protected action screens (duplicate, send) answer 404 on a missing record.
- Order-flow fields that are required answer "gerekli" (the field's required error), not 500.

## What about null in output helpers?
- `h()`, `escape()`, `escape_javascript()`, `escape_csv()`, `encode_url_path()` return "" on null; do not add null guards around them.

## Redirects built from request values?
- Go through `pg_safe_redirect_path()`; never put raw input after `HOSTNAME`.
