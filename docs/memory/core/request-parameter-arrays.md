---
name: request-parameter-arrays
description: How screens must treat a request parameter that arrives as an array (?id[]=x), missing records, and null passed to escaping helpers
---

## What if a single-value parameter arrives as an array?
- It counts as "not given": the HTML is unchanged and nothing answers 500. Check with `is_string()` (GET) before use; never cast an array to string or int.
- `send_to` and `from` are cleaned once, centrally, in `init.php` via `pg_request_single_values()` (`includes/fn/core.php`); do not re-check them per screen.
- ERP: every POST form calls `pg_request_single_values()` for its single-value fields.

## What about the entries of a list a form posts?
- Same rule, one level down: an entry that is itself an array (`choice[email][]=x`, `allocate[5][]=x`, `counted[29][]=x`, `map[0][]=x`, `lines[0][description][]=x`) counts as not given. Check `is_scalar($entry)` before a cast; read a row of fields (`lines[n]`, `categories[id]`) through `pg_request_row_values($row)` (`includes/fn/core.php`), which drops non-scalar fields and keeps numbers from stored rows and JSON bodies.
- Why (PR #476): `(string)` logged "Array to string conversion" and saved "Array" as an invoice/delivery-note line description or a category name; an array count was refused as "not a whole number". Now the description is missing ("Line 1 needs a description."), the category asks for a name, the count line keeps its figure.
- `tests/erp_request_values_test.php` fails when an ERP screen casts or reads a posted list entry without the check.

## What if a record screen gets no id or an unknown id?
- Answer "Kayıt bulunamadı" (`lang('Record not found.')`) with 404. Never fall back to record 1.
- Token-protected action screens (duplicate, send) answer 404 on a missing record.
- Order-flow fields that are required answer "gerekli" (the field's required error), not 500.

## What about null in output helpers?
- `h()`, `escape()`, `escape_javascript()`, `escape_csv()`, `encode_url_path()` return "" on null; do not add null guards around them.

## Redirects built from request values?
- Go through `pg_safe_redirect_path()`; never put raw input after `HOSTNAME`.
