---
name: workspace-command-api
description: GET /workspace/commands (PR #1991): the command registry for the key's owner as WorkspaceCommand rows, workspace:read, no MCP tool, lists only and never runs a command
---

## What does GET /workspace/commands answer?
- Route `workspace.commands.list` in includes/workspace/api.php; handler and presenters in includes/workspace/api_commands.php. workspace:read, no params.
- `ws_commands($viewer)` of the key's owner in registry order (workspace-command-registry.md): a command the owner may not use is left out (/price, /stock, /product without the product rights; /claude, /ai until set up; /schedule, /timer as `ws_command_offered()` says).
- Each row is a `WorkspaceCommand`: key, names, name, label, description, usage, example, args, live. Each arg a `WorkspaceCommandArgument`: name, kind, label, optional, suggest, multiple, anywhere; an empty `suggest` is `null`. Texts in the site's language (`ws_command_turkish()`).

## Why no MCP tool?
- `'mcp' => false`: a model gains nothing from the picker's list; it calls the endpoints a command would (tasks, plan items, scheduled messages, changes).

## Does it run commands?
- No. Listing only; running them over the API is plan question 3 (plans/workspace-slash-commands.md), still waiting for the owner.

## Tests?
- tests/workspace_commands_test.php: the route's declaration, presenters matching their schemas, registry order. tools/check_api_schema.php pairs both schemas with their presenters.
