---
name: api-database-unavailable
description: External API and MCP 503 database_unavailable (a lost database connection, with Retry-After) versus 503 service_unavailable (a feature waiting for its database update)
---

## What does a lost database connection answer?
- 503 `database_unavailable` with `Retry-After: 30`, on both entries: `api_run()` (`includes/api/bootstrap.php`, integration.php) and `pg_mcp_gate()` (`includes/mcp/protocol.php`, mcp.php), whenever `DB_CONNECTED` is not defined.
- Both call `api_fail_database_unavailable()` (bootstrap.php), which sends `api_database_unavailable_answer()` (`includes/api/schema.php`: status, code, headers). A new entry calls the same helper, never `api_fail()` with its own code.
- `api_run()` changed in PR #3911 (issue #3412); the MCP gate kept `service_unavailable` without `Retry-After` until PR #4091 (issue #4027).

## Why a code of its own?
- Routes answer 503 `service_unavailable` when a feature needs a database update first (for example `GET /workspace/time`). A team device client hides that feature; on an outage it should only retry. With one code it had to ask `GET /auth/me` first to tell them apart.
- Feature 503s keep `service_unavailable`; `not_installed` and `api_disabled` are unchanged, and the gates keep their order.

## Where is it declared and tested?
- In `api_error_catalogue()` (`includes/api/schema.php`), which the console's error list and `tools/check_api_schema.php` read.
- `tests/api_database_unavailable_test.php`: the shared answer, and both entries answering through the helper.
