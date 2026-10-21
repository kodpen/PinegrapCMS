---
name: mcp-dry-run-argument
description: MCP tool calls read the dry_run argument by the X-Dry-Run rule (1/true/yes/on, any case); a value that is neither is refused with 400 invalid_dry_run before anything runs
---

## How does an MCP tool call read dry_run?
- `pg_mcp_arguments()` (`includes/mcp/tools.php`) reads it with `api_dry_run_value()` (`includes/api/dry_run.php`), the same rule as the `X-Dry-Run` header and `?dry_run=`: `1`, `true`, `yes`, `on` in any case and with spaces around are a rehearsal; `0`, `false`, `no`, `off`, an empty string, `null` and a missing argument are a write.
- Anything else (`"maybe"`, `2`, an array) is `null`, and `pg_mcp_tool_call()` refuses the call with 400 `invalid_dry_run` ("dry_run must be true or false.", field `dry_run`) right after the scope check, as the API refuses an unreadable header. Nothing runs.
- The tool's input schema still declares `dry_run` as a boolean; the wider reading is for clients and models that send text.

## Why?
- Before the fix of issue #863 the MCP layer counted only `true`, `1`, `'1'` and `'true'` as a rehearsal, case-sensitively. `"yes"`, `"on"` or `"True"` became false, `X-Dry-Run` went out empty and the write really happened while the caller believed it was rehearsing (reproduced with `products_update`).
- An unknown value is refused rather than read as false or true: the header's reasoning (`api_dry_run_requested()`) holds here too, a typo is far more likely than a wish to write.

## Where is it tested?
- `tests/mcp_test.php` (`test_mcp_dry_run_reads_as_the_header_does`). The test loads `includes/api/dry_run.php`, so its gate accepts `PG_API_PANEL` as well as `PG_API_ENTRY`, like the other API files the panel and the tests load.
