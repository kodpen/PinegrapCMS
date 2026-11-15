---
name: approval-agents-api
description: Agents asked to approve over the external API (AE4, #9584): the agents field on create and who may send it, the decide route for an agent's own key, off MCP, WorkspaceApproval.agents
---

## How does an API call ask agents to approve?
- `POST /workspace/channels/{id}/approvals` takes `agents`, each a handle (with or without @, any case), `agent:<id>`, `model:<id>`, `ai` for Pinegrap AI or `claude` (`approval-assistants.md`), up to ten. `ws_api_approval_agent_values()` resolves them against `ws_approval_agent_options($channel)`; an unknown name is refused with 422 at `agents`, never dropped.
- Only the person's own team devices session may name agents (`ws_api_writer_app_id()` 0, the by-hand bound of `approval-agents.md`). An application key or an OAuth connection, MCP clients among them, is an application asking and is refused with the bound's 422.
- The dry run checks the same through `ws_approval_input()` and lists `agents` in its detail.

## How does an agent answer over the API?
- `POST /workspace/approvals/{id}/decide` with the agent's own application key: `ws_api_agent_caller_row()` finds the active `app` agent of the key (no device), marks it seen and hands over to `ws_api_approvals_agent_decide()`, which runs `ws_approval_agent_decide_check()` (dry run included) and `ws_approval_agent_decide()`.
- Claude's key (`ws_api_is_claude()`) goes to `ws_api_approvals_claude_decide()` first (#9622, `approval-assistants.md`).
- Any other key keeps the 403 of `ws_api_person_only()`; a device session answers as the person, as before.
- The route stays `'mcp' => false`: an MCP client never answers in an agent's or a person's name.

## How does WorkspaceApproval show the agents?
- `agents` lists agent (the request value), handle (null once the agent is removed), name, decision, note, decided_at (`ws_api_approval_agent_present()`), after `people` and `guests`.

## What does the line under the card say once an agent answered?
- `ws_approval_agent_decide()` closes the agent's request as answered with no message; `ws_agent_request_replied()` counts an answered request whose `ai_state` carries an approval as a reply (#9584), so the line reads "@handle answered" and `no_answer` stays false, never "saw it · no answer needed".
- Tests: `tests/workspace_api_approval_agents_test.php`.
