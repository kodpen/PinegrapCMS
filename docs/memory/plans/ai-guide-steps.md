---
name: ai-guide-steps
description: Plan ai-guide, steps AG1 to AG7 of fourteen: the guide's engine with the API and MCP doors, the designer topic, the other topics, the panel's door, the compact tool list, presets
---

## Which steps are there?
- AG1 (M) first: the engine and the two doors. Done: #10710.
  - New: `includes/fn/agent_guide.php` (registry, text, list, digest, sizes; all eight topic ids named, each listed only when its builder file is there), `includes/fn/agent_guide_start.php`, `includes/api/resources/guide.php` (`guide.read`, the 304).
  - `includes/api/schema.php` (the route), `includes/mcp/tools.php` (`guide.read` offered without a scope; "Guide: <topic>." in `pg_mcp_tool_guidance()`), `includes/mcp/resources.php`, `includes/mcp/protocol.php` (the two lines, the 3,072 byte test).
  - Tests, red first: sizes, every named tool is an offered route, digest changes with the text, a topic without its file is not listed. Features: G1 to G5, G9, G10 without the prompt, G17.
- AG2 (M) after AG1: the designer topic. Done: #10737.
  - `includes/designer_ai.php`: the assistant's rule lines into one function the prompt and the topic read (the assistant's prompt stays word for word: a test compares); a function listing the `<pg-keep>` kinds.
  - New `includes/fn/agent_guide_designer.php`; `includes/api/resources/design.php` (framework, colours, font families on `design.designs.list`); `includes/mcp/prompts.php` (`design_change`). `php tools/check_api_schema.php`.
  - Tests: the five worked lists run through `pg_design_ai_apply_ops()`; every kind and operation in the text. Features: G6, G7, the prompt of G10.
- AG3 (M) after AG1: `agent_guide_content.php`, `agent_guide_forms.php`, `agent_guide_store.php`. Feature: G5's shape for the three.
- AG4 (S) after AG1: `agent_guide_workspace.php`, `agent_guide_erp.php` (listed only with the module on).
- AG5 (S) after AG1: `agent_guide_panel.php` from the search box's list, with the viewer's gates. Feature: G8.
- AG6 (S) after AG5: the panel's door. New `agent_guide.php` and `llms.txt` answer in the software folder; the `rel="help"` link in the panel's head; "AI agent guide" in `pg_account_menu()`; the Visual Page Editor's link to `designer`. Texts with `php tools/tr_add.php`. Feature: G11.
- AG7 (M) after AG1: the compact list.
  - `includes/oauth/server.php` (the second address and its document), `includes/mcp/protocol.php` and `includes/mcp/tools.php` (the list, `tools_find`, `tools_describe`, `tools_read`, `tools_write`, the money rule), the panel's connection guide (both addresses, the compact one named for AI assistants).
  - Tests: the compact list's bytes (under 8 KB without money tools), a money route refused through `tools_write` and run by its own name, a GET refused through `tools_write` and a write through `tools_read`, scope and validation as the tool's. Tried with a real client on dev; the PR names which. Features: G13, G14.
- The steps after these (presets, budgets and the diet, hints, the report, close-out) and the order of all: `ai-guide-steps-2.md`.
