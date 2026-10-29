---
name: workspace-agent-discussion-follow
description: Plan (2026-10-21; steps 1 #1121, 2 and 3 #2924 done; step 4 left): an assistant a discussion is about, or was asked in it, answers each later message there without its @name; channels unchanged
---

## What is asked? (owner, 2026-10-21)
- Slack's way: Claude (any assistant) was asked with "@claude …" and a discussion was started from its answer in the channel. In that discussion it reads every message and answers without "@claude" being written again. In a channel nothing changes: only `@name`, `/name` or a reply to its answer asks it.
- Done first: assistants read and answer in a discussion their app owner is not in (PR #1085, workspace/workspace-assistant-reach.md).

## What is there today?
- A discussion follows its channel's permission (`ws_claude_channel_allowed()`, `ws_ai_channel_allowed()`). `ws_threads.assistants` (varchar 20, `claude,ai`) only records who was asked, for the line under the channel message (`ws_thread_assistant_asked()`).
- A reply (`parent_id`) to an answer asks again: `ws_claude_answers_claude()`, `ws_ai_answers_ai()`, MCP `ws_mcp_ask_server()`. Requests start in `ws_message_ask_assistants()` (panel send, `POST …/messages`); a scheduled message calls the three hooks itself (scheduled_messages.php); an edit asks no assistant.
- What an assistant reads about a discussion: workspace/workspace-assistant-discussions.md.

## What are the steps? (approved, #2419)
1. Done (PR #1121): Claude's context of a discussion carries `thread` (its channel and the message it talks over), the routine prompt says so.
2. Done (PR #2924): `ws_threads.follow_agent` (step 11.0.113), set at the start and by an assistant asked by name; a person's message that asks and mentions nobody asks it, the scheduled send too; prompt line and settings-card sentence. workspace/workspace-discussion-follow.md.
3. Done with step 2 (PR #2924), so Claude follows at once: no new request while a sent or asked-by-name one is open; a queued follow-only request gives way to the latest message.
4. Screen and API: the discussion header says "@Claude follows this discussion" with Stop / Follow again for anybody in it, a system line records who changed it, the line under the channel message shows it; `follow` on the API's discussion object and an endpoint to change it (a person's own choice: `ws_api_person_only()`). Keys with `php tools/tr_add.php`, workspace.js and `php tools/build_js.php`.
5. Memory topic file, tests (who is followed, what asks, the pile-up rule), plan finished.

## Which questions are answered? (#2419)
1. Every person's message asks the followed agent, but one that mentions only colleagues (b).
2. All three kinds follow, Claude with step 3; the settings card says a followed discussion costs runs (a).
3. Several assistants in one discussion: the last one asked by name is followed (a).
4. Anybody in the discussion may stop the following (a).
5. A discussion started from the request message is followed too.
6. Answers address the latest message (`parent_id`).

## Where to start?
- Step 4 (screen and API, answer 4a) is under way on feature/ws-discussion-follow-4: workspace.js, threads.php `ws_thread_present()`, actions.php, api_threads.php, api.php; `ws_thread_follow_set()` is the write.
