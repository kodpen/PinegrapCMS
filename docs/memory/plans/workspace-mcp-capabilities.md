---
name: workspace-mcp-capabilities
description: Plan (2026-10-23, not started, answers open): an AI app connected over MCP in the Workspace: fewer dead tools, proposals as approval cards, resources and prompts, API gaps
---

## What is asked? (owner, 2026-10-23)
- An AI connected over MCP does more in the Workspace, safely.

## What is there today?
- Tools are routes: ~300 at a full grant (75 Workspace, 137 core, 78 ERP, 13 translations); scopes are consent ticks ∩ OAuth ceiling ∩ rights (`pg_oauth_effective_scopes()`).
- 11 tools refuse all but Claude's own key (`ws_api_is_claude()`): six of `ws_claude_api_routes()`, five `translations.jobs.*`.
- resources/list, prompts/list answer empty (`pg_mcp_run()`).
- Over the API only Claude's key puts a change on a card (`ws_changes_store()` on a `ws_ai_requests` row); an MCP client writes directly, or decides and applies (approvals.decide, changes.apply: `ws_api_person_only()` lets OAuth).
- OAuth clients share one `api_apps` row (kind `oauth`); the client is `api_devices.oauth_client_id`.

## What is proposed? (not approved yet)
- Fewer, truer tools; AI record writes wait on a card the person applies; context as resources; no right widens.

## Slices
1. No schema: the 11 take `'mcp' => false` (API rule: OAuth cannot use them; the routine uses REST). tests/workspace_api_test.php: a handler calling `ws_api_claude_guard()` or `translate_api_claude_guard()` is no tool, like `test_ws_api_own_words_routes_are_no_tools`; update api/api-mcp-server.md.
2. No schema: question 1 (b: `'mcp' => false`, same test).
3. After 2, else a connection applies its own proposal; no schema expected, else ask first: new `workspace.changes.propose`, POST /workspace/channels/{id}/changes (+ task twin), workspace:write, key or OAuth, body as requests.answer: a `ws_ai_requests` row, agent proposed `oauth:<client id>` / `app:<id>` (as plans/workspace-agent-handles.md step 2); `ws_message_send_check()`, `ws_changes_input()`, `api_dry_run_stop()`, then the message (`ws_api_writer_app_id()`), `ws_changes_store()`; the owner applies. Check `agent` readers (`ws_task_run_key()`, queues).
4. No schema: new includes/mcp/resources.php, in `pg_mcp_capabilities()`: `pinegrap://workspace/my-day`, `…/channels/{id}/context`, `…/refs/{type}/{id}` through their GET routes. Prompts (channel summary, weekly report): `pg_ai_prompt_rules()`, plans/ai-agent-prompts.md step 2 framing; safety lines: its step 5.
5. No schema: consent presets (Workspace, store, accounting, all) ticking scopes in includes/oauth/authorize.php.
6. No schema, a PR each: tasks `due_time`, `remind_minutes`; channel customer on create/update (`ws_customer_input()`); forward (forward.php); a small-file path (files.create is `'mcp' => false`).
7. No schema: scheduled actions: list, get, pause, cancel as tools (`ws_scheduled_list()`, `ws_scheduled_present()`, `ws_scheduled_set_status()`); create, edit, resume (`ws_scheduled_save()`, `ws_scheduled_input()`) by question 2; `*_check()` splits for dry_run; `ws_can_schedule()`; after PR #1352 (open, watch.php).
- Seen: ERP account, receipt, expense, translations writes lack `dry_run`.

## Open questions (recommendation)
1. MCP on approvals.decide, changes.apply: (a) as now, (b) not offered; the person uses the card, (c) confirmed again on the site → b.
2. Creating a scheduled action (runs later as its creator, like a scheduled message): (a) team devices only, `'mcp' => false`, (b) OAuth too, saved paused, (c) a, and MCP posts the draft card (`ws_ai_changes` record_type `scheduled`) by slice 3 → c.
3. Direct record writes by AI apps: (a) kept, (b) an OAuth ceiling (every client) without record write scopes, no schema; propose needs workspace:write, (c) a "propose only" config column (schema) → b.
4. Tool profiles: (a) consent presets, (b) tool groups per connection (schema) → a.

## Where to start?
- Slice 1; slice 3 after question 1. Read workspace/workspace-api-callers.md, workspace-api-dry-run.md.
