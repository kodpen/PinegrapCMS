---
name: request-parameter-arrays
description: How screens must treat a request parameter that arrives as an array (?id[]=x), missing records, and null passed to escaping helpers
---

## What if a single-value parameter arrives as an array?
- It counts as "not given": the HTML is unchanged and nothing answers 500. Check with `is_string()` (GET) before use; never cast an array to string or int.
- `send_to` and `from` are cleaned once, centrally, in `init.php` via `pg_request_single_values()` (`includes/fn/core.php`); do not re-check them per screen.
- ERP: every POST form calls `pg_request_single_values()` for its single-value fields.

## What if a record screen gets no id or an unknown id?
- Answer "Kayıt bulunamadı" (`lang('Record not found.')`) with 404. Never fall back to record 1.
- Token-protected action screens (duplicate, send) answer 404 on a missing record.
- Order-flow fields that are required answer "gerekli" (the field's required error), not 500.

## What about null in output helpers?
- `h()`, `escape()`, `escape_javascript()`, `escape_csv()`, `encode_url_path()` return "" on null; do not add null guards around them.

## Redirects built from request values?
- Go through `pg_safe_redirect_path()`; never put raw input after `HOSTNAME`.
