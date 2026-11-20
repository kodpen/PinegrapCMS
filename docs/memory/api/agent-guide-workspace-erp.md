---
name: agent-guide-workspace-erp
description: The guide's workspace and erp topics for AI agents: what each says, when each is listed, the canvas guide pointed at and not copied, sizes and the tool list's bytes
module: agent_guide_workspace, agent_guide_erp
---

## Where do the two topics live?
- `includes/fn/agent_guide_workspace.php` (`pg_agent_guide_workspace_sections()`) and `includes/fn/agent_guide_erp.php` (`pg_agent_guide_erp_sections()`), read by the engine (`agent-guide-engine.md`). Built in #10718 (plan ai-guide, AG4).
- Sections of each: `records`, `jobs`, `writes`, `mistakes`; read one with `guide_read` topic and section.

## When is each listed?
- Through the registry's own rule, no code of their own: workspace while `WORKSPACE_ENABLED` and the caller holds a `workspace.` tool; erp while `ERP_ENABLED` and the caller holds an `erp.` tool. With the module off the topic is neither listed nor answered (404).
- Once listed, every offered `workspace.*` and `erp.*` tool's description ends with "Guide: workspace." or "Guide: erp." (`pg_mcp_tool_guide_line()`).

## What does the workspace topic say?
- Records: channel, message, task, proposal, approval, table, note, memory, flow. Jobs: `workspace_channels_context` before answering, `since_id` to follow, `workspace_search`, messages, tasks, `workspace_changes_propose`, approvals, table rows, canvases and live pages.
- Writes: dry_run first; the application is the writer readers see; at most 10 changes a proposal and 30 proposals an hour; applying is the person's, never a tool's; tags `<#order:1045>`, `<@user:12>`; only a channel's agent posts a canvas or page.
- Drawing: it names `workspace_canvas_guide` before the drawing tool and copies no value of the canvas guide (`workspace/agent-canvas-guide.md`); a test refuses a hex colour in the topic.

## What does the erp topic say?
- Records: account (positive balance owes the store), invoice (draft, issued, return), quote and purchase order, waybill, receipt and expense, till, stock.
- Jobs: dashboard; look up `tax_number` before opening an account; draft then issue; return or cancel an issued invoice, delete a draft; an order's invoice only by `erp_orders_invoice`; receipts with `invoice_id`; stock counts; the VAT, profit, aging and cash flow reports.
- Writes: minor units, send quantities and unit prices; total is subtotal - discount + tax - withholding; issue, cancel and return are for good; a money tool only by its own name, dry_run first; `period_locked` is told, the date never moved.
- An MCP call sends no `Idempotency-Key`, so the topic says to look in `erp_receipts_list` before posting a receipt again.

## What do they weigh?
- Measured at AG4 with every module on: workspace 2,964 bytes, erp 2,666 (limit 4,096); the topic list with start, workspace and erp 352 bytes.
- The full MCP tool list: 854,183 bytes before, 858,569 after (+4,386, the "Guide: <topic>." lines: Workspace +3,402, ERP +984). The opening instructions are unchanged.
- `tests/agent_guide_workspace_erp_test.php`: listing by module and tool, the words each topic must say, draft named before issue.
