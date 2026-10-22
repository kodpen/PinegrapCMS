---
name: api-openapi-nullable-fields
description: When an external API field is declared nullable (string? in the resource's *_schema()), what decides it, and the tests that guard it
---

## Where is "may be null" declared?
- In the resource's `*_schema()` declaration beside its presenter: `'phone' => 'string?'`. `api_openapi_type_spec()` (`includes/api/openapi.php`) turns `?` into `"nullable": true`; its comment holds the rule.

## What decides the question mark?
- What the presenter can hand back, not the column. `?` when some create path leaves the column unwritten or writes NULL, when the presenter answers null itself (`... ? x : null`, no part to refer to), or when the site's shipped data has it null.
- No `?` for a DEFAULT NULL column every create path writes (product/group name, page name/title/meta description, file name/type, form name) or a value the presenter casts (`(string) $row['x']`).
- `api_time()` answers null for 0: a time field without `?` must read a column every create path writes (`created_at`, a task's `updated_at`, a signature's `signed_at`, a decision's moment).
- A missing `?` breaks generated clients on the first null; an extra one only makes them check. Do not remove an existing `?` without reading every create path.

## Which tests guard it?
- `tests/api_openapi_status_test.php`: person fields (#401), other fields (#412), always-written columns and their create paths (#420: a new INSERT that leaves such a column out fails `test_api_openapi_status_create_paths_write_those_columns`, and the field takes `?` in the same change), and for Workspace and ERP every presenter paired by its `// What X() returns.` comment: a null branch or an `api_time()` of another column needs `?` (PR #477; the rescan found none missing).
- A presenter and its schema should stay paired by that comment, or the Workspace/ERP check cannot read it.
