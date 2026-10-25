---
name: workspace-mcp-capabilities
description: Plan (2026-10-23, steps 1-2 done #1468, 4 built, answers 1-2 given): an AI app on MCP in the Workspace: fewer dead tools, one proposal route for every plan, resources and prompts, API gaps
---

## What is asked? (owner, 2026-10-23)
- An AI connected over MCP does more in the Workspace, safely.

## What is there today?
- Tools are routes: ~300 at full grant (75 Workspace, 137 core, 78 ERP, 13 translations); scopes are consent ticks ∩ OAuth ceiling ∩ rights (`pg_oauth_effective_scopes()`).
- 11 tools refuse all but Claude's key: handlers calling `ws_api_claude_guard()` (six of `ws_claude_api_routes()`) or `translate_api_claude_guard()` (five `translations.jobs.*`).
- Resources and prompts (slice 4): api/mcp-resources-prompts.md.
- Only Claude's key makes a change card (`ws_changes_store()`, a `ws_ai_requests` row); an MCP client writes directly or decides and applies (approvals.decide, changes.apply: `ws_api_person_only()` lets OAuth).
- OAuth clients share one `api_apps` row (kind `oauth`); the client is `api_devices.oauth_client_id`.

## What is proposed? (not approved yet)
- Fewer, truer tools; AI record writes as change cards the person applies; context as resources; no right widens.

## Slices
1. No schema: a handler calling either guard takes `'mcp' => false` (the routine uses REST), never all of `ws_claude_api_routes()`: GET /workspace/channels/{id}/context has no guard, stays a tool, feeds slice 4. Test like `test_ws_api_own_words_routes_are_no_tools` (tests/workspace_api_test.php); update api/api-mcp-server.md.
2. No schema: question 1 (b: `'mcp' => false`, same test).
3. After 2 (else it applies its own proposal), no schema: new `workspace.changes.propose`, POST /workspace/channels/{id}/changes (+ task twin), workspace:write, key or OAuth, body as requests.answer: a `ws_ai_requests` row; `ws_message_send_check()`, `ws_changes_input()`, `api_dry_run_stop()`, the message, `ws_changes_store()`; the owner applies. The one proposal route for every plan (catalogue bulk drafts, `erp_quote` creates, scheduled change cards), none per kind. First `oauth:<oauth_clients.id>` (a key: `app:<id>`) joins plans/workspace-agent-handles.md's agent vocabulary; decide its question 5: `ws_api_writer_app_id()` shows one OAuth row for every client (ChatGPT, Cursor alike). Check `agent` readers (`ws_task_run_key()`, queues).
4. Built: resources my-day, channel context, refs by their GET routes; prompts channel_summary, weekly_report with `pg_ai_prompt_rules()` (api/mcp-resources-prompts.md). Left: `…/channels/{id}/customer` after plans/workspace-crm-seams.md slice 2.
5. No schema: consent presets (Workspace, store, accounting, all) in includes/oauth/authorize.php.
6. No schema, a PR each: tasks `due_time`, `remind_minutes`; channel customer on create/update (`ws_customer_input()`); forward (includes/workspace/forward.php); a small-file path (files.create is `'mcp' => false`).
7. Scheduled actions as tools: plans/workspace-canvas-workflow-tables.md S1, S2.
8. No schema, a PR per area: `dry_run` on 9 of 42 ERP writes lacking it (includes/erp/api.php: accounts, expenses, receipts) and all 7 translations writes.
- Each new change kind (`erp_quote`, `variant_set`, bulk create) also reaches the requests.answer `changes` text, `ws_ai_tools()` and the propose route's text (its MCP tool).

## Which questions are answered? (owner, 2026-10-24)
1. MCP on approvals.decide, changes.apply: (a) as now, (b) not offered (the card), (c) confirmed again → b.
2. Direct record writes by AI apps: consent presets leave record write scopes unticked by default, the propose route always there (not kept as is, no OAuth ceiling, no column).
Open: 3. Tool profiles: (a) consent presets, (b) tool groups per connection (schema) → a.

## Where to start?
- Slice 1; slice 3 after question 1. Read workspace/workspace-api-callers.md, workspace-api-dry-run.md.
