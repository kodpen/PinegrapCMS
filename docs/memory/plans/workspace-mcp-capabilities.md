---
name: workspace-mcp-capabilities
description: Plan (2026-10-23, steps 1-2 done #1468, 3 #1564, 4 #1598, answers 1-2 given): an AI app on MCP in the Workspace: fewer dead tools, one proposal route for every plan, resources and prompts, API gaps
---

## What is asked? (owner, 2026-10-23)
- An AI connected over MCP does more in the Workspace, safely.

## What is there today?
- Tools are routes: ~300 at full grant (75 Workspace, 137 core, 78 ERP, 13 translations); scopes are consent ticks ∩ OAuth ceiling ∩ rights (`pg_oauth_effective_scopes()`).
- 11 tools refuse all but Claude's key: handlers calling `ws_api_claude_guard()` (six of `ws_claude_api_routes()`) or `translate_api_claude_guard()` (five `translations.jobs.*`).
- Resources and prompts (slice 4): api/mcp-resources-prompts.md.
- A change card comes from an assistant's answer or an application's proposal (slice 3); an MCP client still writes records directly where its scopes allow, and is offered no card decision (slice 2).
- OAuth clients share one `api_apps` row (kind `oauth`); the client is `api_devices.oauth_client_id`.

## What is proposed? (not approved yet)
- Fewer, truer tools; AI record writes as change cards the person applies; context as resources; no right widens.

## Slices
1. No schema: a handler calling either guard takes `'mcp' => false` (the routine uses REST), never all of `ws_claude_api_routes()`: GET /workspace/channels/{id}/context has no guard, stays a tool, feeds slice 4. Test like `test_ws_api_own_words_routes_are_no_tools` (tests/workspace_api_test.php); update api/api-mcp-server.md.
2. No schema: question 1 (b: `'mcp' => false`, same test).
3. PR #1564, no schema: `workspace.changes.propose` (POST /workspace/channels/{id}/changes) and `workspace.tasks.changes.propose`, the one proposal route for every plan (catalogue bulk drafts, `erp_quote` creates, scheduled change cards), none per kind: workspace/workspace-change-proposals.md. Agents `oauth:<oauth_clients.id>`, `app:<id>`; a proposal's own message shows the client, other OAuth messages keep the shared row (plans/workspace-agent-handles.md question 5 stays open).
4. PR #1598: resources my-day, channel context, refs by their GET routes; prompts channel_summary, weekly_report with `pg_ai_prompt_rules()` (api/mcp-resources-prompts.md). Left: `…/channels/{id}/customer` on the route of #1622.
5. No schema: consent presets (Workspace, store, accounting, all) in includes/oauth/authorize.php.
6. No schema, a PR each: tasks `due_time`, `remind_minutes`; channel customer on create/update (`ws_customer_input()`); forward (includes/workspace/forward.php); a small-file path (files.create is `'mcp' => false`).
7. Scheduled actions as tools: plans/workspace-canvas-workflow-tables.md S1, S2.
8. No schema, a PR per area: `dry_run` on 9 of 42 ERP writes lacking it (includes/erp/api.php: accounts, expenses, receipts) and all 7 translations writes.
- Each new change kind (`erp_quote`, `variant_set`, bulk create) also reaches `ws_api_changes_param()` (the `changes` text of requests.answer and both propose routes, so their MCP tools) and `ws_ai_tools()`.

## Which questions are answered? (owner, 2026-10-24)
1. MCP on approvals.decide, changes.apply: (a) as now, (b) not offered (the card), (c) confirmed again → b.
2. Direct record writes by AI apps: consent presets leave record write scopes unticked by default, the propose route always there (not kept as is, no OAuth ceiling, no column).
Open: 3. Tool profiles: (a) consent presets, (b) tool groups per connection (schema) → a.
Open: 4. An application's proposal is applied over REST from the team devices session only, no OAuth connection (slice 3, else a client applies its own card): keep?

## Where to start?
- Slices 1-3 are in: slice 4 or 5 next. Read workspace/workspace-api-callers.md, workspace-change-proposals.md.
