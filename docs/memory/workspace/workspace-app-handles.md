---
name: workspace-app-handles
description: How an application tag <@app:N> is drawn: Claude's link reads @Claude, Pinegrap AI's @ai, any other app a handle from its own name; deleted and former Claude apps
---

## What does a tag read as?
- `ws_refs_resolve()` (refs.php) reads every tagged application's `api_apps` row in one query and asks `ws_app_tag()` (app_handles.php).
- The application linked as Claude (`ws_claude_config()['app_id']`) reads "@Claude", Pinegrap AI's (`ws_ai_config()['app_id']`) "@ai", Pinegrap AI first when both point at one. They keep that name while the link points at them, even switched off or with their row deleted.
- Any other application reads "@<handle>" from its own name, the name as meta, icon `bi-plug`. Before this every such tag read "@Claude", in the panel and in the `text` of `workspace.app.mentioned`.

## How is the handle made?
- `ws_app_handle($name, $app_id, $taken)`: Turkish and common accented letters folded, lower case, any other run of characters one dash, starts with a letter, cut to 32, no dash or underscore at either end: the shape `ws_mcp_alias_shape_problem()` accepts.
- Never one of `ws_mcp_reserved_aliases()` (so an app named "Claude" that is not linked reads "@app-N") nor an MCP server's alias (`$taken`, read only when such an app is drawn). Otherwise, or when the name gives fewer than 2 characters, `app-<id>`.
- Two applications with one name share a handle: the token says which one; uniqueness and a person's user name wait for the registry (plans/workspace-agent-handles.md step 2, open question 3).

## What do a deleted and a former application read as?
- No row: "@app-N", meta "Deleted record", `known` false, `gone` true, like any tag whose record is gone.
- An application Claude used before the link moved: no history of the link is kept, so its old tags read as that application itself ("@claude-routine"), or "@app-N" when its name is a reserved word.

## Where is it tested?
- tests/workspace_app_handles_test.php. The @ picker (workspace.js) does not offer applications yet: step 1b of the plan.
