---
name: workspace-voice-mcp-agents
description: A voice message that asks an MCP agent (VA6): the microphone on its messages, ws_mcp_agent_heard() reads the transcript, the ask after the transcript, discussions
---

## Why was the agent left out before?
- The server's own agent (agent mode, mcp_agent.php) built its request from the words alone (`ws_mcp_agent_text()`), so a recording would have reached it empty; the panel hid the microphone on its messages (`voiceAnswers()`). Pinegrap AI asked with a server's tools (mcp_ask.php) already read transcripts through `ws_ai_heard_text()`.

## What does VA6 change?
- `voiceAnswers()` (workspace.js) is true for any one message: the reply bar's microphone and the sound's own microphone answer an MCP agent's message as they answer Claude's and Pinegrap AI's.
- `ws_mcp_agent_heard($asker, $row, $limit)` (mcp_agent.php): the words through `ws_mcp_agent_text()`, then the recording's transcript or the reason nobody heard it, joined by `ws_voice_heard()` (pure part `ws_mcp_agent_heard_text()`, the words alone where voice.php is not loaded). The request and the message a reply answers both go through it; the alias is stripped from the request after the join (`ws_mcp_agent_unaddressed()`).
- Nothing new on the server for the ask: `ws_voice_ask()` calls `ws_message_ask_assistants()`, which calls `ws_mcp_agent_after_send()`; `ws_mcp_ask_server($body, $message_id)` finds the agent by the alias in the tags or, with tags alone beside the microphone, by the request whose answer the recording replies to (`ws_ai_requests.reply_message_id`, agent `ai` or `mcp`). `ws_attach` answers `agent` when a recording asked at once (transcripts off) and the screen starts it (`kickAgent(true)`); after the transcript the screen carries a queued `mcp` request on from `schedule()`.
- A discussion: `ws_thread_agents_asked()` finds the agent the same way, so a recording answering an agent's message there asks it and the discussion follows it (`mcp:<id>`), as a written reply does (workspace-discussion-follow.md).

## What was checked?
- tests/workspace_voice_agents_test.php: `voiceAnswers()` under Node, the pure join with a transcript, a reason and another file, and the wiring of the request, the ask after the transcript, the screen and the discussion. Not run against a live agent nor in a browser.
