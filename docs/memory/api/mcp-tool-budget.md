---
name: mcp-tool-budget
description: The MCP tool size budget (AG9): 600 characters a description, 4,096 bytes a tool, every tool held to both by the test since AG12b, and the route key details only OpenAPI reads
---

## What are the budgets?
- A tool's description (summary, description and guidance as `tools/list` sends it) at most 600 characters (`mb_strlen`); the whole tool as JSON (`pg_mcp_json_encode()`) at most 4,096 bytes. `pg_mcp_tool_budgets()` and `pg_mcp_tool_over_budget()` in `includes/mcp/tools.php`.
- Why: every tool on the full address is read before the first question; long tools cost a client its context. Built in plan ai-guide step AG9 (feature G16), PR #10822.

## How does the test hold them?
- `tests/mcp_tool_budget_test.php` builds every route of the core and the modules as tools for a caller with every scope and fails on any tool over either budget. There is no exceptions list: a new or longer tool over a budget is fitted, never listed.
- Before AG12b the test kept shrink-only lists of the tools over each budget; AG12b (#11155) emptied them and removed them.
- On 2026-11-20: 584 tools, 222 descriptions over 600 characters, 13 tools over 4,096 bytes.
- AG10 (Workspace, PR #10920): all 102 Workspace descriptions and its 7 tools over 4,096 bytes fit; the test lists hold no `workspace_` tool. The tool list fell from 883,515 to 793,568 bytes (Workspace 356,741 to 266,794).
- AG10 moves: the flow boxes' settings went to `$flow_details` (flows create and update), the proposals' `changes` text to `ws_api_changes_details()` (a brief form via `ws_api_changes_param(..., true)`), the canvas short guide to `details`.

## Where does long reference text go?
- A route row's `details` key: read only by `api_openapi_operation_description()` (`includes/api/openapi.php`), which writes description, the scope sentence, then the details as their own paragraph. `pg_mcp_tool_from_route()` never reads it (tested).
- What a caller needs for a right call stays in the description, a parameter's description or the guide section the tool's "Guide: <topic>." line names; only lookup detail moves to `details`.
- The diet steps (AG10 Workspace, AG11 ERP, AG12 the rest) take tools off the lists; each PR body gives the tool list's bytes before and after.
- AG11 (ERP, `includes/erp/api.php`): all 28 ERP descriptions fit; events announced, panel parallels, field glossaries and Idempotency-Key advice moved to `details`; the cheque steps went to the `step` parameter. The test's tool list fell from 883,515 to 876,234 bytes (ERP 122,760 to 115,479).
- AG12a (Translations, `includes/translate/api.php`, PR #11129): `translations_strings_list`, `translations_refresh` and `translations_status` fit; the status meanings moved to the `status` parameter, where each text is used, the screen's Update translations and Approve all to `details`. The tool list fell from 801,056 to 800,504 bytes (605 tools). 

## Why does the test fix the guide line?
- `pg_mcp_tool_guide_line()` reads the modules on once a request (static), so its "Guide: <topic>." depends on what ran first: present in `php tools/test.php mcp`, empty in the full run. The test strips it and adds the line a site with store, workspace and erp on sends, so the lists are the same in every run.
- AG12b (`includes/api/schema.php`, PR #11155): the last 89 long descriptions and 6 tools over 4,096 bytes fit; answer glossaries, events, screen parallels, full 409 explanations, 503 notes and IIS background moved to `details`; the forms fields guidance in `pg_mcp_tool_guidance_own()` shortened. The tool list fell from 803,588 to 757,053 bytes (608 tools).
- Close to a budget after AG12b: `products_update` and `store_shipping_methods_create` sit within 20 bytes of 4,096; `design_bundles_publish` at 590 characters.
- Route tests that check a moved phrase read description and `details` together (`api_order_returns_test.php`, `api_returns_test.php`, `shipment_label_api_test.php`).
