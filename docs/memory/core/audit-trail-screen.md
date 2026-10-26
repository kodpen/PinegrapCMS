---
name: audit-trail-screen
description: The audit trail screen view_audit_log.php and its CSV export (includes/fn/audit_view.php): who may open it, filters, how each stored value form is shown, export limits and its own line
---

## What is it?
- `view_audit_log.php` (manager and up, like the Site Log; Settings hub, Monitoring group): `audit_log` lines newest first, 50 a page (one line more is read to know an older page exists; no COUNT). Nothing on it writes a line except the CSV export.
- Helpers in `includes/fn/audit_view.php` (pure, `tests/audit_view_test.php`): `pg_audit_view_filters()`, `pg_audit_view_where()`, `pg_audit_view_changes()`, `pg_audit_view_value()`, `pg_audit_view_csv_line()`, `pg_audit_view_record_url()`, `pg_audit_view_label()`.

## Which filters are there?
- From/to (whole days in the site's time zone; the picker's text is turned to ISO first, a reversed range is turned round), source (`panel`, `api`, `job`), user (a number is `user_id`, else part of `username`, where an API line keeps the app's name), action and type (the choices are `SELECT DISTINCT` of the trail), record id, request id, text in `label`. A value that does not fit its field is dropped, never passed into SQL; LIKE wildcards in the text are literal.
- Links on a line narrow the list: the name shows the record's whole history (type + id), the action, the user, the request id (every line one request wrote). The icon opens the record's screen for product, page, file, user, order, offer, the API screen for apps, keys, devices and OAuth clients.

## How is a stored value shown?
- A side keeps only changed fields. `null` is "—" (no value on that side), `***` a "Hidden" badge (a secret, `core/audit-trail-stored-values.md`), an object or list as indented JSON, a compacted text (`truncated`, `length`, `sha1`, `start`) as its opening, its size and its SHA-1 in the title. A side cut to `{truncated, fields}` shows the names with "Too large to keep". A real field named `truncated` beside others stays a field.

## What does the CSV hold?
- The filtered lines, newest first by id, read 1000 at a time, at most 50,000 (`pg_audit_view_limits()`); columns id, time, source, user, IP, action, type, record id, name, request id, the changed field names, and both sides as stored. UTF-8 with BOM; a value starting with `=`, `+`, `-`, `@` gets a leading apostrophe.
- Each export writes its own line first: action `export`, type `audit`, the filters and the format in the after side.

## What is not here yet?
- `GET /audit-log` and the retention setting with its job: plan `privacy-data-pack-audit-log` steps 5b and 5c.

Related: docs/memory/core/audit-trail-stored-values.md, docs/memory/core/audit-trail-writers.md, docs/memory/plans/privacy-data-pack-audit-log.md
