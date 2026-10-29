---
name: workspace-discussion-follow-screen
description: Stop / Follow again for the assistant a discussion follows (PR #2997, answer 4a of #2419): header line, channel line, system line, API follow fields and endpoint
---

## Who may stop or restart the following?
- Anybody in the discussion while it is open (answer 4a of #2419); somebody who only reads the channel sees the line but no button. One check for the panel and the API: `ws_thread_follow_refusal()` (not in it, concluded, schema not there, Follow again with no agent ever asked).
- The write is `ws_thread_follow_change()` (threads.php) over `ws_thread_follow_set()`: the discussion gets a system line "X stopped @Claude following the discussion" / "X asked @Claude to follow the discussion again"; asking for what is already so writes nothing. Panel action `ws_thread_follow` (`thread_id`, `follow` 0/1).
- Asking an assistant by name later makes it the followed one again (workspace-discussion-follow.md), whatever was stopped.

## Which agent does Follow again bring back?
- No column keeps it (no schema change): `ws_thread_follow_last()` takes the agent of the discussion's latest `ws_ai_requests` row (`mcp` → `mcp:<server id>`), else the agent of the message the discussion is about (`ws_thread_message_agent()`), else the last of `ws_threads.assistants`.

## What do the screens show?
- `ws_thread_present()` adds `follow`, `follow_handle`, `follow_again`, `follow_again_handle`, `can_follow`; `ws_thread_follow_state()` (pure) decides them. The handle is `@Claude`, `@ai` or an MCP server's `@alias` (`ws_thread_follow_handle()`), '' once the server is gone.
- Discussion panel: a line under the header, "@Claude follows this discussion" with Stop, or only Follow again once stopped (`view.followLine()` in workspace.js). Nothing goes into the channel menu or the discussion's ⋮ menu.
- Under the channel message: "@Claude follows this discussion" beside the "was asked" line.

## What does the API give?
- `WorkspaceThread` carries `follow` (claude, ai, mcp:<id> or null), `follow_handle`, `follow_again`, `can_follow` (`ws_api_thread_follow_fields()`).
- `POST /workspace/threads/{id}/follow` with `follow` true/false (`workspace.threads.follow`): `ws_api_person_only()`, dry run, no MCP tool; 403 not in it, 422 concluded or nothing to follow again.

## Where are the tests?
- tests/workspace_thread_follow_test.php: the state, the handle, the route (person only, rehearsal before the write, system line), the API fields in schema order. No dev DB here: the screen was not clicked through.
