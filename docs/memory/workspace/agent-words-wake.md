---
name: agent-words-wake
description: An application's API message and an agent's answer wake the agents they name or answer: only a mention or a reply counts, the asking application named, the braked line once an hour
---

## Which messages written by an application wake agents?
- `POST /workspace/channels/{id}/messages` with an application's key, or through an OAuth connection, kind message only (`ws_api_message_wakes_agents()`), and an agent's answer (`POST /workspace/agent-requests/{id}/answer`). Both call `ws_agent_after_app_send()`, which calls `ws_agent_after_send()` and nothing else.
- Claude, Pinegrap AI and the MCP aliases are never asked by an application's words (`ws_api_message_asks_assistants()` stays a person's only). A woken model agent is carried on as from a person's message (`ws_api_assistants_carry_on()`).
- The symptom it fixed (owner, 2026-11-11): the architect's API message naming `@prmerge` made no request and no "Waiting for" line, and the reply made none for the architect.

## Which wake rules count for them?
- Only a mention (`@handle`, `<@app:N>`) and "Replies to its messages" (`ws_agent_wake_matches()`, `$by_app`). `all` and `prefix` never match an application's line, so CI's `[ci]` lines wake nobody.
- The writer is an application when the message's sender is `app` or it came through an OAuth connection (`ws_agent_chain_entry()`'s `by_agent`). Its own words never wake an agent.
- The "Waiting for @agent" line shows under an agent's message as under a person's: it reads the request by message id.

## How does the request name the asking application?
- It is read from the asking message's sender (`ws_agent_request_asker_app()`); `requested_by` stays the writer person (the application's owner, or the person it writes through).
- The API's request (`WorkspaceClaudeRequest`) carries `requested_by.app_id`, `app_handle`, `app_name` (0 and '' for a person).
- The brief says who asked ("the agent @mimar (ADNAN Mimar)", or "the application <name>") and that the answer wakes it only when it names it or its rule wakes it on replies; `no_answer` when nothing needs saying.
- Such an answer gets no `<@user:N>` in front (a person's request does, `ws_agent_answer_text()`), so the owner is not pinged by agents talking.

## What do the brakes do between agents?
- They stay: chain depth, hourly count, never its own words (`ws_agent_brake()`).
- A braked wake under a person's message leaves its system line every time; under an application's message once per channel and hour for the same line (`ws_agent_brake_line_due()`), so a chain going on without the braked agent does not repeat it.
