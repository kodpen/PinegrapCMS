---
name: workspace-api-overview
description: The Workspace part of the external API as a whole: who may call which write (key, OAuth, team devices), dry runs, sync and inbox, tasks, plan, time, forwards; which workspace-api-* file holds each
---

## What is it?
- The `workspace.*` routes of the external API (`/workspace/...` in `includes/workspace/api*.php`) and the MCP tools made from them (`api/api-mcp-server.md`): what an integration, a team devices app or an assistant reads and writes in channels, messages, tasks, notes, the plan and time.
- Every route reuses the panel's own function and its checks: an application meets the refusals a person meets on the screen.

## Which decisions bind it?
- Access, the scope ceiling `ws_owner_scopes()` and what an API change must remember: `workspace-module-decisions.md`.
- Who decides on a card (approvals, applying a proposed change) and which writes stay the person's own: `workspace-api-callers.md` (its own owner decision).
- A connected application's message shows under the person's name: `workflow/decisions-made-workspace-11.md`. Publishing a flow only from the person's own team devices session: `workflow/decisions-made-product.md` (#1708).

## Which callers are there? (what a person sets up in the panel)
- An application key (an API key): writes as the application. An OAuth connection (a person's Claude, ChatGPT): writes in the person's name, but never their own words. A team devices session (`POST /auth/login`): the person themselves (`api/api-device-sessions.md`, `agent-sessions.md`).
- `ws_api_person_only()` and `ws_api_own_words_only()` sort the writes between them: `workspace-api-callers.md`. A layout the same for everybody (a channel into a group) is no personal preference: `workspace-api-layout-writes.md`.

## Which parts are there?
- Rehearsal: every write takes `X-Dry-Run` (the console's "Can be rehearsed"), through the panel's `*_check()` functions: `workspace-api-dry-run.md`.
- Staying current: `GET /workspace/sync`, the inbox's `changed_since`, hidden messages, assistants carried on after the answer: `workspace-api-sync-inbox.md`.
- Channels: a channel's customer on create and update (`workspace-api-channel-customer.md`); forwarding messages, the Forward of the panel, and the `forwards` of every message (`workspace-api-forward.md`).
- Tasks: due time and e-mail reminder (`workspace-api-task-reminder.md`); task time and My time across tasks, changing an entry (`workspace-api-task-time.md`); which reads hand out tasks only with `tasks:read` (`workspace-api-tasks-scope.md`).
- Plan board: `GET /workspace/plan`, a row and a day as the board draws them (`workspace-api-plan.md`).

## Where is each part?
- Writing a new endpoint (Site Log name through `ws_api_as_owner()`, bell rows, search scopes per record type, files, events): `workspace-api-endpoint-rules.md`; general API rules: `api/api-endpoint-rules.md`, `api/api-dry-run.md`, webhooks `api/api-webhook-events.md`.
- Proposing record changes, an application's write only: `workspace-change-proposals.md`. Flows over the API: `workspace-flows-overview.md`.

## How is a part added?
- A route row in `includes/workspace/api.php` with its scope (also in `ws_owner_scopes()`), `'dry_run' => true`, the handler calling the shared `*_check()`, then `api_dry_run_stop()`, then the write (`workspace-api-dry-run.md`).
- Decide the caller: the person's answer, their own words, or an ordinary write; such a route carries `'mcp' => false` where an assistant must not call it (`workspace-api-callers.md`).
- A part sync sends only when it changed writes an optional cursor key (`workspace-api-sync-inbox.md`); a new `workspace.*` webhook event carries `channel_id` (`workspace-api-endpoint-rules.md`).
- Then a line in the file of its part here, or a new `workspace-api-<name>.md` named in this file.
