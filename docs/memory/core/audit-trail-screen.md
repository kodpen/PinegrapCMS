---
name: audit-trail-screen
description: The audit trail screen view_audit_log.php, its CSV export and GET /audit-log (includes/fn/audit_view.php): who may open it, filters, how each stored value form is shown, export limits
module: audit_view
---

## What is it?
- `view_audit_log.php` (manager and up, like the Site Log; Settings hub, Monitoring group): `audit_log` lines newest first, 50 a page (one line more is read to know an older page exists; no COUNT). Nothing on it writes a line except the CSV export.
- Helpers in `includes/fn/audit_view.php` (pure, `tests/audit_view_test.php`): `pg_audit_view_filters()`, `pg_audit_view_where()`, `pg_audit_view_changes()`, `pg_audit_view_value()`, `pg_audit_view_csv_line()`, `pg_audit_view_record_url()`, `pg_audit_view_label()`.

## When does a line get an "Open" link?
- Only when its record still exists: `pg_audit_view_live_records()` looks up the page's ids with one query per type (`pg_audit_view_record_tables()`: products, page, files, user, orders, offers, contacts), and the screen drops the link of a missing one. The name's history link stays. Before (#2521), deleted pages and files linked to their edit screen's 404. API types keep their `api_settings.php` link.

## Which filters are there?
- From/to (whole days in the site's time zone; the picker's text is turned to ISO first, a reversed range is turned round), source (`pg_audit_sources()`: `panel`, `api`, `job`, `site`), user (a number is `user_id`, else part of `username`, where an API line keeps the app's name), action and type (the choices are `SELECT DISTINCT` of the trail), record id, request id, text in `label`. A value that does not fit its field is dropped, never passed into SQL; LIKE wildcards in the text are literal.
- Links on a line narrow the list: the name shows the record's whole history (type + id), the action, the user, the request id (every line one request wrote). The icon opens the record's screen for product, page, file, user, order, offer, the API screen for apps, keys, devices and OAuth clients.

## How is a stored value shown?
- A side keeps only changed fields. `null` is "—" (no value on that side), `***` a "Hidden" badge (a secret, `core/audit-trail-stored-values.md`), an object or list as indented JSON, a compacted text (`truncated`, `length`, `sha1`, `start`) as its opening, its size and its SHA-1 in the title. A side cut to `{truncated, fields}` shows the names with "Too large to keep". A real field named `truncated` beside others stays a field.

## What does the CSV hold?
- The filtered lines, newest first by id, read 1000 at a time, at most 50,000 (`pg_audit_view_limits()`); columns id, time, source, user, IP, action, type, record id, name, request id, the changed field names, and both sides as stored. UTF-8 with BOM; a value starting with `=`, `+`, `-`, `@` gets a leading apostrophe.
- Each export writes its own line first: action `export`, type `audit`, the filters and the format in the after side.

## What does the API give?
- `GET /audit-log` (`includes/api/resources/audit_log.php`, scope `system:read`: roles 0-1 may grant it, assistant keys are made without it): the same filters through `pg_audit_view_filters()` / `pg_audit_view_where()`, `from`/`to` as moments, cursor by id, newest first. An `action`, `entity_type` or `request_id` the screen would drop is 422, never an unfiltered answer. `before`/`after` are the sides decoded as stored, null when empty.

## Does it replace the Site Log?
- No. The free-text `log_activity()` and `view_log.php` stay and keep feeding the Activity card; `audit_log` is the structured record beside them.

## How long are lines kept?
- 2 years by default, set on the Security screen; the general job removes older ones: `core/privacy-log-retention.md`.

Related: docs/memory/core/audit-trail-stored-values.md, docs/memory/core/audit-trail-writers.md
