---
name: workspace-app-handles
description: How an application tag <@app:N> is drawn (Claude's link @Claude, Pinegrap AI's @ai, any other app a handle from its own name) and which apps the @ picker offers per channel
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

## Which applications does the @ picker offer?
- Per channel, `ws_channel_detail()` sets `apps` from `ws_channel_app_picker()`: one query on `api_webhooks` joined to `api_apps` (both active, the subscription active or failing) whose events hold `workspace.app.mentioned`; a subscription narrowed to a group counts only when the group is in the channel's chain (`ws_webhook_event_groups()`, a discussion by its channel), as the queue matches it. Then `ws_app_hears_mentions()` and `ws_app_reads_channel()` for the owner: the conditions `ws_message_tell_apps()` asks, so the picker never offers an app the mention would not reach (PR #1975).
- Claude's and Pinegrap AI's applications are left out: they have their own entries. Each entry is drawn with `ws_app_tag()` (label and shown `@handle`, meta the name, token `<@app:N>`), so the picked text is the tag as it reads.
- Guest rooms and channels shared with guests get none, like the assistants. workspace.js lists them after the MCP aliases and before the people (the list is cut at ten), matched by handle or name prefix; never in a note.

## Where is it tested?
- tests/workspace_app_handles_test.php: handles, tags and the pure picker filter `ws_app_picker_entries()`.
