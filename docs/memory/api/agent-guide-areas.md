---
name: agent-guide-areas
description: The guide's content, forms and store topics (agent_guide_content/forms/store.php): sections, which tools each names, values read from their registries, money tools named, the store prefixes, bytes
module: agent_guide_content, agent_guide_forms, agent_guide_store
---

## What do the three topics hold?
- Each declares four sections: `records` (the area's records, a line each), `jobs` (the tool for each job), `writes` (what a write needs first), `mistakes` (frequent mistakes). The engine and its rules: `agent-guide-engine.md`.
- content (`pages.`, `folders.`, `files.`): page settings vs layout (a page's text is the designer topic's), folder access rules, upload types, a new page is `design_pages_create` in the drafts folder, `access_widening_refused`, recycle bin and `permanent`.
- forms (`forms.`): form = page, field by name, submission, collection; read `forms_fields` first; which field types an update writes; pictures through `files_create` with `form_id`; `send_notifications` only when asked.
- store (`products.`, `product_groups.`, `inventory.`, `orders.`, `store.`): SKU name, minor units, stock only through `inventory_write`/`inventory_bulk`, order statuses and types, Idempotency-Key, lists that replace.
- Built in AG3 of plan ai-guide, PR #10722.

## Which values must follow code?
- The text carries lists other code owns; `tests/agent_guide_areas_test.php` compares them, so a changed list fails the test: folder access rules = `api_folder_access_ranks()` keys in order; upload types = `api_files_allowed_types()`; the update's field types = `api_form_text_field_types()` plus single-answer pick list and radio button (`api_form_field_updatable()`); order statuses = `api_order_statuses()`.
- Every offered route under a topic's prefixes marked `moves_money` must be named in that topic: today `orders_create` and `orders_labels_create`, in the store topic's line telling the agent to ask the person before each call.

## Why does store list five prefixes?
- The topic covers stock and variants, so `inventory.` and `product_groups.` sit beside `products.`, `orders.`, `store.` in `pg_agent_guide_registry()`: those tools also end with "Guide: store." over MCP.

## How large are they?
- Measured at AG3 with every module on: content 2,562 bytes, forms 2,520, store 2,375 (limit 4,096).
- "Guide: <topic>." on the tool list (a space and the line, MCP only): store 82 tools, 1,148 bytes; content 14 tools, 224; forms 11 tools, 154; 1,526 bytes in all. The opening instructions are unchanged (2,859 bytes).

## What is a test-runner trap here?
- `pg_mcp_tool_guide_line()` reads the modules actually on (`pg_agent_guide_modules_on()`); the test runner defines no `ECOMMERCE`, so a store tool gets no guide line there. `test_agent_guide_tool_guidance_line` expects the topic on the modules that are on, not on all of them.
