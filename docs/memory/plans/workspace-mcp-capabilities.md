---
name: workspace-mcp-capabilities
description: Plan (2026-10-23, steps 1-6 done, answers 1-2 given): an AI app on MCP in the Workspace: fewer dead tools, one proposal route, resources and prompts, API gaps
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
5. PR #1716: consent presets (Workspace, Store, Accounting, All) on the consent screen (api/api-oauth-consent-presets.md); what each ticks waits on question 3.
6. No schema, a PR each: tasks `due_time`, `remind_minutes` (PR #1742, workspace/workspace-api-task-reminder.md); channel customer on create/update (PR #1766, workspace/workspace-api-channel-customer.md); forward (PR #1816, workspace/workspace-api-forward.md); small-file path (PR #1830, api/workspace-mcp-small-file.md).
7. Scheduled actions as tools: plans/workspace-canvas-workflow-tables.md S1, S2.
8. No schema, a PR per area: `dry_run` on the ERP writes lacking it (accounts: PR #1850; left: receipts, expenses; erp/erp-api-dry-run.md) and all 7 translations writes.
- Each new change kind (`erp_quote`: done #1705; `variant_set`, bulk create) also reaches `ws_api_changes_param()` (the `changes` text of requests.answer and both propose routes, so their MCP tools) and `ws_ai_tools()`.

## Which questions are answered? (owner, 2026-10-24)
1. MCP on approvals.decide, changes.apply: (a) as now, (b) not offered (the card), (c) confirmed again → b.
2. Direct record writes by AI apps: consent presets leave record write scopes unticked by default, the propose route always there (not kept as is, no OAuth ceiling, no column).
Open: 3. Tool profiles: (a) consent presets, (b) tool groups per connection (schema) → a.
Open: 4. An application's proposal is applied over REST from the team devices session only, no OAuth connection (slice 3, else a client applies its own card): keep?

## Where to start?
- Slices 1-6 are in (slice 6: #1742, #1766, #1816, #1830). Next: slice 8 receipts, then expenses, then translations, a PR each.
