---
name: agent-doors
description: The agents' request queue: wake rules make one ws_ai_requests row per agent (app:<id>, model:<id>), the brakes, the push knock and its retry, the agent-requests routes
---

## When does a message make an agent's request?
- `ws_message_ask_assistants()` calls `ws_agent_after_send()` (`includes/workspace/agent_queue.php`, loaded from agents.php) for every message, a person's or an application's. Each allowed `ws_channel_agents` row of an active `app` or `model` agent is matched by `ws_agent_wake_matches()`: its `@handle` as a word or its application's `<@app:N>` tag under every rule; `all` every non-empty message; `prefix` a message starting with the prefix (any case); `replies` an answer to one of the agent's own messages. Its own message never wakes it.
- One row per woken agent: `ws_ai_requests.agent` = `app:<agent id>` or `model:<agent id>` (the registry row's id, not the application's), status queued, the channel and message, `requested_by` the writer.
- The brakes come first (`ws_agent_brake()`): the reply chain read back through `parent_id` (`ws_agent_chain()`, agent messages counted until a person's) and the agent's requests of the last hour. A braked wake leaves a system line naming the agent.
- Claude keeps its own queue and routine (`ws_claude_after_send()`); Pinegrap AI and the MCP agents too.

## How is a push door knocked?
- At once for an `app` agent with door `push`: `ws_agent_knock()` POSTs `{"event":"workspace.agent_request","site":<integration.php address>,"agent":<handle>,"request_id":N}`, never the words. With a door secret: `Authorization: Bearer <secret>`, `X-Pinegrap-Timestamp`, `X-Pinegrap-Signature: sha256=hmac(timestamp + "." + body)`. 10 s timeout.
- A 2xx clears `door_failures` and `door_error`; anything else counts a failure with its sentence, `door_last_knock` the time. `ws_agent_knocks_retry()` (top of `ws_ai_kick()`, so screens and the hourly job) knocks again for the newest queued request once `ws_model_hold_seconds(failures)` has passed (1 min doubling, 1 h at most). The request stays queued for a poll meanwhile.
- A poll door gets no knock: the agent reads `GET /workspace/agent-requests`.

## Which routes does an app agent use?
- `api_agent_requests.php`, `'mcp' => false` (the key used as itself, `ws_api_agent_caller()`: an active `app` row whose `app_id` is the caller, no device): `GET /workspace/agent-requests` (open, or a status, or all), `POST …/{id}/claim` (409 already_claimed / closed; 👀 by the agent's application), `…/answer` (text under the asking message with the asker mentioned, or `no_answer`; written through `ws_assistant_writer()`; 403 when the channel row no longer allows the agent or the asker cannot write there; ✅), `…/fail` (reason told under the message when it can be). Requests are shown as Claude's are (`WorkspaceClaudeRequest`). Everything else the agent does goes through the API's own routes and MCP tools.
- A model agent's request is answered by the site (`model-assistants.md`); the screen and `ws_api_assistants_carry_on()` carry it on as Pinegrap AI's (`models` in the ask's result).

## What is still to come (A4b)?
- Wake rows for schedules and `at <time>` from the general cron job, `/wake @agent …` in the channel and the API, the full brief (preamble, context, memory digest, loop-guard text, MCP address and scopes), the request shown on the message, own-message edits with an edited mark, `ws_assistants()` taking the registry agents (picker, tags, follow).
- Never tried live: a knock against a real receiver, the routes with a real key.
