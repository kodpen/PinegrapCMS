---
name: workspace-assistant-discussions
description: An assistant asked in a discussion: what it reads about it (Claude's context thread, PR #PRNUM; Pinegrap AI's first message), where it may be asked, what is planned
---

## What does an assistant read in a discussion?
- A discussion is a channel row of kind `thread` about one message of a channel (threads.php): its own messages are the context, and what it is about comes beside them.
- Claude: `GET /workspace/channels/{id}/context` of a discussion carries `thread`, the channel it was started in (id, name, kind) and the message it talks over (id, kind, sender_kind, author, text, task_id, created_at; `message` is null once that message was deleted). `thread` is null for a channel (`ws_api_context_thread_present()`, objects `WorkspaceContextThread` and `WorkspaceContextMessage`, PR #PRNUM). Before it Claude saw only "X started this discussion" and the replies, not what they were about.
- The routine prompt says what `thread` is (`ws_claude_routine_prompt()`). A routine whose prompt was pasted earlier still gets the field; copying the prompt again from the settings card adds the sentence.
- Pinegrap AI's first message says "This is a discussion about message N of the channel #…", with that message (`ws_ai_start()`).
- An MCP agent reads only the message and the one it answers (a decision, mcp_agent.php).

## Where may it be asked?
- Wherever it may be asked in the discussion's channel (`ws_claude_channel_allowed()`, `ws_ai_channel_allowed()`). Where its app owner is not in the discussion it reads and answers through the person who asked: workspace-assistant-reach.md.
- Being asked there is kept in `ws_threads.assistants` for the line under the channel message (`ws_thread_assistant_asked()`).

## What is planned?
- Answering every later message there without its @name: plans/workspace-agent-discussion-follow.md.

## Where are the tests?
- tests/workspace_discussion_context_test.php (presenter, declaration, the prompt's sentence). Checked in the sandbox: a discussion's context named its channel and message, a channel's had `thread` null, the OpenAPI document declares both objects as nullable references.
