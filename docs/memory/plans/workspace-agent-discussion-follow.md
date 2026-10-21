---
name: workspace-agent-discussion-follow
description: Plan (2026-10-21, step 1 done #PRNUM, answers open): an assistant a discussion is about, or was asked in it, answers each later message there without its @name; channels unchanged
---

## What is asked? (owner, 2026-10-21)
- Slack's way: Claude (any assistant) was asked with "@claude …" and a discussion was started from its answer in the channel. In that discussion it reads every message and answers without "@claude" being written again. In a channel nothing changes: only `@name`, `/name` or a reply to its answer asks it.
- Done first: assistants read and answer in a discussion their app owner is not in (PR #1085, workspace/workspace-assistant-reach.md).

## What is there today?
- A discussion follows its channel's permission (`ws_claude_channel_allowed()`, `ws_ai_channel_allowed()`). `ws_threads.assistants` (varchar 20, `claude,ai`) only records who was asked, for the line under the channel message (`ws_thread_assistant_asked()`).
- A reply (`parent_id`) to an answer asks again: `ws_claude_answers_claude()`, `ws_ai_answers_ai()`, MCP `ws_mcp_ask_server()`. Requests start in `ws_message_ask_assistants()` (panel send, `POST …/messages`); a scheduled message calls the three hooks itself (scheduled_messages.php); an edit asks no assistant.
- What an assistant reads about a discussion: workspace/workspace-assistant-discussions.md.

## What is proposed? (not approved yet)
1. Done (PR #PRNUM): Claude's context of a discussion carries `thread` (its channel and the message it talks over), the routine prompt says so. Left for step 2: the prompt line that a request in a followed discussion may not name Claude and answers the latest messages.
2. Follow (schema, ask first): `ws_threads.follow_agent` (`claude`, `ai`, `mcp:<server id>`, later the agent keys of plans/workspace-agent-handles.md; empty for none). Set by `ws_thread_start()` when the message is an assistant's answer or a request to one, and when an assistant is asked by name inside. `ws_message_ask_assistants()` and the scheduled send queue a request for it when a person's message there asks nobody else; the usual checks stay (allowed there, waiting limits, MCP account).
3. No pile-up: while a request of the followed agent in this discussion is still queued or sent, a new message opens none; that request answers the latest messages (Claude reads the context when it runs, Pinegrap AI builds its first message when the call starts). One Claude run then serves a lively discussion.
4. Screen and API: the discussion header says "@Claude follows this discussion" with Stop / Follow again for anybody in it, a system line records who changed it, the line under the channel message shows it; `follow` on the API's discussion object and an endpoint to change it (a person's own choice: `ws_api_person_only()`). tr.json, workspace.js and `php tools/build_js.php`.
5. Memory topic file, tests (who is followed, what asks, the pile-up rule), plan finished.

## Open questions (recommendation)
1. Which messages ask the followed agent: (a) every person's message, (b) all but one that mentions only colleagues, (c) only questions → b.
2. Claude's daily run allowance (Pro 5, Max 15, Team 25): (a) follow with step 3, (b) follow only Pinegrap AI and MCP agents, (c) a per-discussion switch → a, the settings card says a followed discussion costs runs.
3. Several assistants in one discussion: (a) the last one asked by name is followed, (b) all asked follow, (c) only the first → a.
4. Who stops following: (a) anybody in the discussion, (b) its starter only → a.
5. A discussion started from the request message rather than the answer: follow too → yes.
6. Answers addressed to the latest message (`parent_id`) or to the discussion as a whole → the latest message.

## Where to start?
- Step 2: ask the owner for its schema step first (CLAUDE.md rule 5) and for open questions 1-4.
