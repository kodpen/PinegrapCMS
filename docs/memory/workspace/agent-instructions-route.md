---
name: agent-instructions-route
description: GET /workspace/agent-instructions, read with an application agent's own key: the text's parts in order (who, channels, door, requests, never-list, rules), the three doors, its version
---

## What does the route give?
- `GET /workspace/agent-instructions` (`workspace.agent_instructions.get`, `'mcp' => false`, `workspace:read`, `ws_api_agent_caller()`): `{handle, name, role, site, door, instructions_version, text}` (type `WorkspaceAgentInstructions`). Route, handler and schema in `includes/workspace/api_agent_instructions.php`; the text in `agent_instructions.php`.
- Why: a shipped site has no repository guide, so an agent given only its key and the Site API must learn from the site how to wait and answer (Owner (2026-11-15): an agent finds its way in the channels with no CLAUDE.md, which a shipped site never has).

## What is in the text, in order?
- `ws_agent_instructions_text($agent, $channels, $base)`, pure, English: who it is (name, `@handle`, role label), the Site API and HTTP Basic from `PINEGRAP_KEY` / `PINEGRAP_SECRET`; its allowed, unarchived channels (`ws_agent_instructions_channels()`) with what wakes it (`ws_agent_instructions_wake()` per `wake_rule`).
- How it waits (`ws_agent_instructions_door()`: poll, or push split into routine when `ws_claude_url_valid()` accepts the address, else webhook). Poll: the list every 15 s, never under 5 s, and one `curl` loop line that stops when the list is not `"data":[]`. Webhook: the call names site, handle and id only; read the list on start too. Routine: one run a fire, ending when the list is done.
- Each request: claim (409 already_claimed / closed skip, editing retry), brief, then answer text, `no_answer` (+ one emoji) or fail; 409 edited, 403; a wake request. The never-list; reading the text again when a brief names another version; `pg_ai_prompt_rules('assistant')` last.
- Never the key, the secret or the door's address: only the variable names.

## What is the version?
- `instructions_version` = first 8 of `sha1(text)` (`ws_agent_instructions_version()`), as Claude's. No claim is refused by it. The brief naming it is AS3's step.
- Tests: tests/workspace_agent_instructions_test.php (poll, webhook, routine doors, order, no channel, no secret).
