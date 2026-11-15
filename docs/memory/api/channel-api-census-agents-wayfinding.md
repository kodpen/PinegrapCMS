---
name: channel-api-census-agents-wayfinding
description: CA1b census, what an application agent meets when it finds its way with the API alone: the brief's MCP line that names tools which do not exist, refusals that name no next step, a route named by id
---

## Does every call the instructions and the brief name exist?
- Yes over HTTP: `ws_agent_instructions_text()` names agent-instructions, agent-requests (list, claim, brief, answer, fail), agent-wake and `POST /workspace/channels/{id}/messages`; the brief names answer, fail, `GET /workspace/channels/{id}/context` and, for an approval, `POST /workspace/approvals/{id}/decide`. All are rows of the schema.

## What does the brief promise that is not there?
- `ws_agent_brief_tool_lines()`: "the site's MCP server at … (every API route is a tool there) and the same routes over HTTP, both with your own key". Every route of an agent's own (requests, brief, claim, answer, fail, agent-wake, agent-report, agent-instructions, approvals decide) is `'mcp' => false`: an agent that follows the line over MCP finds no tool to claim or answer with. G6.

## Which refusals name no next step?
- Answer, 403: "The answer cannot be posted there any more: the agent is no longer allowed in that channel, or the person who asked can no longer write there." The request stays open; the words do not say to close it with fail. G7.
- Answer on a wake with channel 0, 422: names `workspace.messages.create`, a route id, where every other line names a method and path. G7.
- Answer's route description: "Other things the agent does (a task, a poll, a proposal) go through their own routes", none named; the instructions name no poll, approval or changes route either. G7.
- Every route of an agent's own, 403: "Only an active application agent, with its own key, may use this endpoint." A paused agent learns nothing of why; the Agents card holds the status.

## What has a next step already?
- Claim's 409s (already_claimed, closed, editing, edited, conversation_cleared) say what happened; answer's 409 edited says to claim again and read the new words.
- An agent-wake time gone: "That time has passed. Choose a time to come."
