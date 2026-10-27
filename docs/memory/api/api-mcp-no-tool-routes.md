---
name: api-mcp-no-tool-routes
description: Which API routes carry 'mcp' => false and are no MCP tools: the polled sync, file downloads, own-words routes, Claude's queues, the person's decisions on a card, and why
---

## Which routes carry `'mcp' => false`, and why?
- `GET /workspace/sync` that an app polls, file downloads (a tool result cannot carry the bytes), and own-words routes an OAuth client could never call (`workspace/workspace-api-callers.md`).
- Posting a file into a channel is a tool, for a small file only: `workspace-mcp-small-file.md`.
- Claude's queues: every route whose handler calls `ws_api_claude_guard()` (`workspace.claude.requests.list`/`claim`/`answer`/`fail`, `workspace.claude.change_types`, `workspace.claude.record`) or `translate_api_claude_guard()` (the five `translations.jobs.*`). They answer only the key of Claude's application, never an OAuth connection, and the routine calls them over REST, so as tools they would refuse every client. Keyed on the guard, not on `ws_claude_api_routes()`: `workspace.channels.context` has none and stays a tool (`test_ws_api_claude_only_routes_are_no_tools`).
- Prototype share links (`design.prototype_links.*`): no AI app mints a public link to unpublished pages (`designer/designer-prototype-share-links.md`).
- The person's decisions on a card: `workspace.approvals.decide`, `workspace.changes.apply`, `workspace.changes.dismiss` (owner decision 2026-10-24: made on the card). REST: a device session or an OAuth connection (a key: 403); an application's proposal only the team devices session (`ws_api_proposal_decision_gate()`, `workspace/workspace-api-callers.md`).

Related: `api-mcp-server.md`, `workspace-mcp-small-file.md`, `workspace/workspace-api-callers.md`.
