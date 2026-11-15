---
name: agent-instructions
description: How an application agent learns its work from the site: GET /workspace/agent-instructions with its own key, the text's parts, the three doors, the version and the brief's line, the Start text box
---

## Why does the site serve the instructions?
- Owner (2026-11-15): an agent must find its way in the channels with no `CLAUDE.md`; a shipped site has no repository, memory or `tools/ws_wait.php`. A session given only the start text and the key answers a mention without being told a command.

## What does the route give?
- `GET /workspace/agent-instructions` (`workspace.agent_instructions.get`, `workspace:read`, `'mcp' => false`): only an active `app` agent's own key (`ws_api_agent_caller()`); any other caller 403. Returns `{handle, name, role, site, door, instructions_version, text}`, type `WorkspaceAgentInstructions`. Route, handler, schema: `includes/workspace/api_agent_instructions.php`; text: `agent_instructions.php` (#9603).

## What is in the text, in order?
- `ws_agent_instructions_text($agent, $channels, $base)`, pure, English: who it is (name, `@handle`, role label, the Site API, HTTP Basic from `PINEGRAP_KEY` / `PINEGRAP_SECRET`); its allowed, unarchived channels with what wakes it (`ws_agent_instructions_wake()` per `wake_rule`).
- How it waits, by `ws_agent_instructions_door()`: poll; push split into routine (`ws_claude_url_valid()` accepts the address) or webhook. Poll: the list every 15 s, never under 5 s, one `curl` loop line that sleeps on an empty list or an empty read (a network failure) and stops on anything else (a request, an error body). Webhook: the call names site, handle and id; read the list on start too. Routine: one run a fire, ending when the list is done.
- Each request (claim and its 409s, brief, the optional progress note (`agent-progress-note.md`), answer / `no_answer` + one emoji / fail, 409 edited, 403, a wake request), the never-list, reading again on another version, `pg_ai_prompt_rules('assistant')` last.
- Never the key, the secret or the door's address: only the variables' names.

## What is the version rule?
- `instructions_version` = first 8 of `sha1(text)` (`ws_agent_instructions_version()`); it changes with the agent's channels, door, role or a release's wording. No claim is refused by it.
- Every brief (`ws_agent_brief()`) ends its tool lines with one line, `ws_agent_instructions_brief_line()`: the route and the version served now; an agent that follows another version reads the instructions again before it starts.

## What was left out?
- A waiting (long-poll) read of the queue: it would hold a PHP worker per agent on shared hosting. The instructions as an MCP resource. The request routes stay as they were.

## Where does a person get the start text?
- The "Start text for the agent" box in an application agent's drawer on the Agents card (#9600): `agent-start-text.md`. It holds only the bootstrap that reads this route.
- Tests: tests/workspace_agent_instructions_test.php, tests/workspace_agent_start_text_test.php.
