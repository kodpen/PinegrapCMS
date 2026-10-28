---
name: db-q-converted-calendars-small-functions
description: Calendar and contact group screens, SMS and mail queues, sign-in, consent log, passkeys, WAF unban, order claim, idempotency and ERP audit on db_q(): PR, special cases, what stayed raw, test.
---

## Which files are converted?
- PR #2371, 22 calls: `get_calendar_view_screen_content.php` 1, `get_calendar_event_view_screen_content.php` 1, `add_calendar.php` 2, `add_calendar_event_location.php` 2, `add_contact_group.php` 2, `developer_lock.php` 2, `check_if_file_exists.php` 1, `includes/install_design_template.php` 1 (the shutdown rename-back), `includes/settings/security.save.php` 1 (Google client secret), and in `includes/`: `fn/waf_rescue.php` 1 (unban DELETE), `fn/sms.php` 1, `fn/signin.php` 1 (`user_identities` insert), `fn/privacy.php` 1 (`consent_log`), `fn/passkeys.php` 1, `fn/mail_queue.php` 1, `fn/ai_crawlers.php` 1, `api/outbound/orders.php` 1 (the `marketplace_order_map` claim), `api/idempotency.php` 1, `erp/audit.php` 1.

## Which special cases were kept?
- Values written unescaped into quotes (`$user['id']`, `(int)` casts, the timestamps) are bound as `(string)`: the SQL text still reads `'5'`.
- The SMS `reference`, the ERP audit `description` (empty) and `detail` (missing) bind `null`, which `pg_db_bind()` writes as `NULL`, as the old ternaries did.
- `consent_log` and `user_passkeys` take their column list from a fixed row array: `array_fill(0, count($row), '?')` and `array_map('strval', array_values($row))`, every value quoted as before (`null` as `''`).
- The callers that avoided `db()` so a failure would not end the request (SMS, mail queue, sign-in, passkeys, order claim) keep that: `db_q()` returns `false` too, and `mysqli_errno()` still reads 1062 after it.

## What stayed raw and why?
- No values: `calendars.php`, `organize_contacts.php`, `get_robots.txt.php`, `includes/settings/registry.php`, `includes/fn/backup.php`, `includes/mcp/protocol.php`, `includes/api/resources/discovery.php`, the calendar lists in the two calendar view screens, the WAF unban read.
- Completed by a built clause: `$order_by` (`view_calendars.php`, `view_calendar_event_locations.php`, `view_contact_groups.php`, `view_visitor_reports.php`), `$where_calendar_id`, `pg_visitor_text_joins()` (`view_visitor.php`), `pg_visitor_ip_clear_sql()` (`fn/visitor_ip.php`), the subscription writes over `$where` (`fn/form_subscription.php`).
- Generic runners of a built query: `pg_record_file_rows()` (`includes/record_files.php`), `erp_query()` (`includes/erp/ledger.php`).
- `includes/fn/custom_form.php` was held by an open PR and is still to do.

## What guards it?
- `tests/db_q_small_functions_test.php`: no escaped value in a `db_q()` SQL, no value in a raw query outside the clauses above, one value per `?`, and the NULL / per-column binds.

Related: docs/memory/data/db-q-converted-files.md (conversion rules), docs/memory/data/db-q-placeholder-helper.md
