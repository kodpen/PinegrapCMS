---
name: api-database-unavailable
description: External API 503 database_unavailable (a lost database connection, with Retry-After) versus 503 service_unavailable (a feature waiting for its database update)
---

## What does a lost database connection answer?
- `api_run()` (`includes/api/bootstrap.php`) answers any route with 503 `database_unavailable` and `Retry-After: 30` when `DB_CONNECTED` is not defined. It answered `service_unavailable` before PR #3911 (issue #3412).

## Why a code of its own?
- Routes answer 503 `service_unavailable` when a feature needs a database update first (for example `GET /workspace/time`). A team device client hides that feature; on an outage it should only retry. With one code it had to ask `GET /auth/me` first to tell them apart.
- Feature 503s keep `service_unavailable`; `not_installed` and `api_disabled` are unchanged.

## Where is it declared and tested?
- In `api_error_catalogue()` (`includes/api/schema.php`), which the console's error list and `tools/check_api_schema.php` read.
- `tests/api_database_unavailable_test.php`.
