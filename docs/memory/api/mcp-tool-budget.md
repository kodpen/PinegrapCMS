---
name: mcp-tool-budget
description: The MCP tool size budget (AG9): 600 characters a description, 4,096 bytes a tool, the shrink-only lists in the test, and the route key details only OpenAPI reads
---

## What are the budgets?
- A tool's description (summary, description and guidance as `tools/list` sends it) at most 600 characters (`mb_strlen`); the whole tool as JSON (`pg_mcp_json_encode()`) at most 4,096 bytes. `pg_mcp_tool_budgets()` and `pg_mcp_tool_over_budget()` in `includes/mcp/tools.php`.
- Why: every tool on the full address is read before the first question; long tools cost a client its context. Built in plan ai-guide step AG9 (feature G16), PR #10822.

## How does the test hold them?
- `tests/mcp_tool_budget_test.php` builds every route of the core and the modules as tools for a caller with every scope, and keeps two lists: tools over 600 characters, tools over 4,096 bytes.
- The lists may only shrink: a tool over a budget that is not listed fails (fit it, never list it); a listed tool that fits fails until it is taken off.
- On 2026-11-20: 584 tools, 222 descriptions over 600 characters (workspace 102, erp 28, design 11, the rest 81), 13 tools over 4,096 bytes (7 of them workspace). The plan's 209 was counted before later routes landed.
- AG10 (Workspace, PR #10920): all 102 Workspace descriptions and its 7 tools over 4,096 bytes fit; the test lists hold no `workspace_` tool. The tool list fell from 883,515 to 793,568 bytes (Workspace 356,741 to 266,794).
- AG10 moves: the flow boxes' settings went to `$flow_details` (flows create and update), the proposals' `changes` text to `ws_api_changes_details()` (a brief form via `ws_api_changes_param(..., true)`), the canvas short guide to `details`; the `workspace` guide topic gained a Building a flow section (assign values, the marketing consent rule).

## Where does long reference text go?
- A route row's `details` key: read only by `api_openapi_operation_description()` (`includes/api/openapi.php`), which writes description, the scope sentence, then the details as their own paragraph. `pg_mcp_tool_from_route()` never reads it (tested).
- What a caller needs for a right call stays in the description, a parameter's description or the guide section the tool's "Guide: <topic>." line names; only lookup detail moves to `details`.
- The diet steps (AG10 Workspace, AG11 ERP, AG12 the rest) take tools off the lists; each PR body gives the tool list's bytes before and after.
- AG11 (ERP, `includes/erp/api.php`): all 28 ERP descriptions fit; events announced, panel parallels, field glossaries and Idempotency-Key advice moved to `details`; the cheque steps went to the `step` parameter. The test's tool list fell from 883,515 to 876,234 bytes (ERP 122,760 to 115,479).
- AG12a (Translations, `includes/translate/api.php`, PR #PRNUM): `translations_strings_list`, `translations_refresh` and `translations_status` fit; the status meanings moved to the `status` parameter, where each text is used, the screen's Update translations and Approve all to `details`. The tool list fell from 801,056 to 800,504 bytes (605 tools). AG12b takes the rows in `includes/api/schema.php`.

## Why does the test fix the guide line?
- `pg_mcp_tool_guide_line()` reads the modules on once a request (static), so its "Guide: <topic>." depends on what ran first: present in `php tools/test.php mcp`, empty in the full run. The test strips it and adds the line a site with store, workspace and erp on sends, so the lists are the same in every run.
