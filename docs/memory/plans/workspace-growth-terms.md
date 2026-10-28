---
name: workspace-growth-terms
description: Shared names and single owners across the Workspace growth plans (2026-10-23): workflow tables, Workspace canvas, the two quote kinds, registries, engine words, agent keys, one proposal route
---

## Which names do the plans share?
- Workflow tables: `ws_wtables`, `ws_wtable_columns`, `ws_wtable_rows`, `ws_wtable_shares`; `ws_wtable_*` in includes/workspace/wtables.php; screen workspace_wtables.php; events `workspace.wtable.*`; `ws_refs` source `wtable_row` (all proposed). UI "Workflow table" / "İş akışı tablosu". Never `ws_table_*`: Markdown note tables use it (`ws_calc_grid()`, `openTable()`).
- Workspace canvas: a flow drawn on workspace_flows.php; `ws_flows`, `ws_flow_versions`, `ws_flow_runs`, `ws_flow_run_steps` (proposed). UI "Flow" / "Akış", made of "boxes" and "arrows". A bare "canvas" in memory means the visual designer's.
- "Board view (the channel Board tab component)", never "plan board" (`manage_workspace_board`, /workspace/plan).
- Quotes, never mixed and never a bare "quote" (a quoted message: `ws_message_quotes`; tr.json "Quote" = "Alıntı"):
  - ERP: kind `erp_quote`, "Sales quote" / "Satış teklifi", tag prefixes `satis`, `salesquote` (table `erp_quotes` exists).
  - Site: kind `cart_quote`, `cart_quotes` / `cart_quote_items` (proposed), events `cart_quote.*`, "Cart quote" / "Sepet teklifi", prefixes `sepet`, `cartquote`.
  - "Offer" (`offers`, a discount) is a third thing and keeps `teklif` until the owner decides.
- Registries: record kinds `ws_record_kinds()` (refs.php); slash commands `ws_commands()` (commands.php); agents `ws_assistants()` (delegation.php).
- Engine: "scheduled action" (`ws_scheduled_actions`, kind ENUM action/message stays), "step" (`ws_scheduled_do()`), "rule" (at, event, trigger); "flow" is a published graph of boxes on the flows engine (a hidden scheduled action is its trigger); "workflow mode" compiles table rows into a flow's task boxes. `ws_event()` queues triggers in `ws_events_in`; `ws_events` is the calendar.
- Agent keys: `oauth:<oauth_clients.id>` (integer), `app:<api_apps.id>`, `model:<id>`, today's `mcp` + `mcp_server_id`; vocabulary owned by plans/workspace-agent-handles.md.
- "Change card" is a `ws_ai_changes` row; "task drafts" are `ws_ai_drafts`.

## Which single owner holds a shared piece?
- One proposal route for every plan: `workspace.changes.propose` (POST /workspace/channels/{id}/changes, plus a task twin): plans/workspace-mcp-capabilities.md. No per-kind proposal routes.
- Direct record writes by AI apps (one owner question): the MCP plan; catalogue and quotes follow its answer.
- Scheduled-actions API and who may create actions: plans/workspace-canvas-workflow-tables.md (S1, S2).
- Flows (canvas, rules, process templates), their engine and step library: plans/workspace-flows.md, plans/workspace-flows-engine.md.
- One follow-up opener `ws_followup_task_open()` (proposed): plans/workspace-crm-seams.md; quote expiry and plans/workspace-phase3-leftovers.md item 4 call it.
- Saved replies: plans/workspace-composer-tools.md. Channel `/` commands: plans/workspace-slash-commands.md; other plans add registry entries (`/product` after its slice 2).
- Contacts in the audit trail and erasure: core/audit-trail-writers.md, core/personal-data-erasure.md; the CRM merge and erasure share one list of every `contact_id` holder (with `cart_quotes` and workflow-table cells).
- Recorded audio and transcripts: plans/workspace-voice-messages.md; live dictation: composer tools.
- The site's own MCP server: plans/workspace-mcp-capabilities.md; outside MCP servers the Workspace calls: plans/workspace-mcp-connectors.md.
