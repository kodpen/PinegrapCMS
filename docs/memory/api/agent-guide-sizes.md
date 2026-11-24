---
name: agent-guide-sizes
description: The MCP tool list measured before the AI agent guide (2026-11-20) and at its close-out (2026-11-24): bytes, tools, the largest areas, the compact list, the opening instructions
---

## How was it measured?
- As `tests/mcp_tool_budget_test.php` builds the list: every route of the core and the modules as a tool for a caller holding every scope, every module on, each tool as JSON. Measure again the same way so the numbers compare.

## What were the numbers?
- Before (2026-11-20, every module and scope): 577 tools, 853,922 bytes (about 210,000 tokens), descriptions 338 KB, 209 over 600 characters, the longest tool 12 KB.
- After (2026-11-24, measured at the close-out as `tests/mcp_tool_budget_test.php` builds the list): 615 tools, 766,202 bytes (about 190,000 tokens); descriptions 246,710, input schemas 368,978; longest description 600 characters, longest tool 4,096 bytes. Workspace 205 tools 272,030 bytes, ERP 85 / 115,581, design 36 / 40,967.
- The compact list with every scope: 21 tools (the five and 16 money tools), 29,632 bytes; the five alone 4,225. Opening instructions 2,881 bytes.

## Where are the limits?
- Tool budget: `mcp-tool-budget.md`. Guide and instructions limits: `agent-guide-engine.md`. Compact list: `mcp-compact-list.md`.
