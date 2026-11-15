---
name: channel-api-census-agents
description: Start file of the CA1b census (2026-11-15): the agents' side of a channel in the panel beside the /workspace/ routes and MCP tools; which part file holds what, the count of gaps
---

## What does this census compare?
- Every thing done in the panel about a channel's agents (Channel settings' "Agents in this channel", the agent card, the person card, /wake, approvals and polls that ask or are answered by agents) and every call an agent makes, beside the routes under `/workspace/` and their MCP tools. Checked against `api_schema()` rows on `development`, 2026-11-15.
- A channel's own side (messages, polls as such, settings, members, memory): CA1a, `channel-api-census-channel.md`.
- Start: `api-panel-coverage.md` (2026-11-07, #7967); no agent route existed then beside Claude's.

## Which file holds which part?
- The channel's agents, model assistants, the two cards: `channel-api-census-agents-channel.md`.
- An agent's own calls, its questions, approvals and proposals: `channel-api-census-agents-calls.md`.
- What an agent meets finding its way with the API alone: `channel-api-census-agents-wayfinding.md`.
- The gap list: `channel-api-census-agents-gaps.md`. No route on purpose, and what a route does differently: `channel-api-census-agents-differences.md`.

## How many gaps are there?
- Seven: five routes or route fields a panel action has (G1-G5), two of the words an agent reads (G6-G7). Each names the function that already does the work.

## Which MCP tools are there?
- Every row without `'mcp' => false` is a tool (`api-mcp-server.md`). Every route an agent calls with its own key is `'mcp' => false`: requests, brief, claim, answer, fail, agent-wake, agent-report, agent-instructions, approvals decide.
