---
name: workspace-mcp-agent-identity
description: One avatar and icon per MCP server asked by its alias ('mcp:<id>'), drawn the same by every server-side place (ws_mcp_agent_identity()); the catalogue entry found by URL; the name kept '@alias'
---

## What is an MCP agent drawn with?
- `ws_mcp_agent_identity($server, $server_id = 0)` (mcp_catalogue.php): `avatar` = `pg_initials_avatar_url(pg_initials(alias), 'contact', 'mcp<id>')`, `icon` = the catalogue entry's Bootstrap icon when the server's address is a ready-made connector, else `bi-plug`; also `initials`, `seed` and `catalogue` (the entry key or ''), so a later avatar change needs no second lookup.
- The initials come from the alias (what the agent is called in tags), never the server name.
- An icon that is not a `bi-…` class falls back to `bi-plug` (it goes into a class attribute).
- A deleted server (`null` row, its id given): the letters of "mcp", its id's colour, `bi-plug`. `ws_mcp_agent_identity_for_id()` reads the row and falls back the same way.

## How is the catalogue entry found?
- `ws_mcp_servers` stores no catalogue key. `ws_mcp_catalogue_entry_for_url($url)` compares `ws_mcp_url_key()` of the server's address with each entry's, as `ws_mcp_catalogue_added()` does; '' when none.

## Where is it used?
- `ws_assistants()` (delegation.php) for every `mcp:<id>`; `ws_assistant_avatar()` for a switched-off or deleted one; `ws_mcp_agent_answer_view()` (the channel sender) and `ws_mcp_agent_inbox_describe()` (mcp_agent.php); the `task_assistant` inbox line (notify.php), which before gave an alias's run Claude's `bi-stars`.
- The channel's @ picker (workspace.js `mcpAliases`): `ws_mcp_boot()` gives each server through `ws_mcp_boot_entry()` (alias, name, agent, avatar, icon); the row draws the avatar, `server.icon` then `bi-plug` without one. Label `@alias` and the meta line unchanged.
- A task's delegate tag (workspace_task_delegate.js `delegateIcon()`): the task brief (tasks.php) carries `delegate_icon` = `ws_assistant_mcp_icon()` (delegation.php; `ws_mcp_agent_identity_for_id()`'s icon for `mcp:<id>`, '' for any other key); the delegation detail's `delegate` carries the same `icon`. The tag draws it when it is a `bi-…` class, else `bi-plug`; `ai` stays `bi-cpu`, Claude and apps `bi-stars`.
- Before this one agent showed two pictures: `ws-ai.svg` in the task's assistant list and the letters avatar on its channel answers.

## What stays as it was?
- `name` in `ws_assistants()` stays `'@alias'`: `ws_task_delegation_detail()` sends the server name as `title` and the delegate tag draws `name · title` ("DeepWiki · DeepWiki" otherwise); `ws_assistant_name()` feeds texts in delegation.php and api_resources.php.
- A plain alias answered by Pinegrap AI is drawn in the channel as Pinegrap AI (`ws_app_sender()`, messages.php).

## Where is it tested?
- tests/workspace_mcp_avatar_test.php: entry by URL, icon fallback, letters and seed, same identity from a full and a slim row, the deleted server, `ws_mcp_boot_entry()`, `ws_assistant_mcp_icon()` for keys that are no alias, and `delegateIcon()` under Node. The `mcp:<id>` path of `ws_assistant_mcp_icon()` reads the server row (no database in tests); the drawn picker and tag are checked by hand.
