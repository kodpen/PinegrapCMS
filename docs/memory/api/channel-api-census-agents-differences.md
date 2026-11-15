---
name: channel-api-census-agents-differences
description: CA1b census: the agents' side that has no route on purpose (with the memory file that says why) and what a route does differently from the panel
---

## What has no route on purpose?
- The Agents card of Site Settings and Workspace Settings: the registry (new agent, handle, door, push address and secret, poll, key, role, pause), the start text, a model assistant's provider, model, key, address, Try and token limit, the activity buttons Cancel, Ask again and Knock now, the report's words: `api-settings-no-endpoint.md`, `workspace/workspace-agent-settings.md`.
- A vote on an agent question, a person's approval answer, applying or setting aside a change: `workspace/workspace-api-callers.md`.
- Undo of a change an agent applied within its tier: a person's act on the card (`workspace/agent-risk-tiers.md`).
- Naming agents on an approval from an application: the by-hand bound (`workspace/approval-agents.md`).
- Claude as a channel agent: `workspace/claude-not-channel-agent.md`.
- A waiting read of the queue and the instructions as an MCP resource: `workspace/agent-instructions.md`.

## What does a route do differently from the panel?
- Channel settings open only for a manager, so the panel offers "Add an agent" to no one else; the set route takes it from whoever may post, and a private channel's new agent from its manager only (`workspace/channel-agent-rights.md`).
- Tier: the panel disables its choices for a non-manager; the route answers 403. Remove does not ask `ws_channel_agent_enter_problem()`.
- Claude: never offered in the panel; the route refuses with 422.
- The agent row gives raw facts (state, percent, resets_at); the panel draws words (`ws_channel_agent_state_words()`, wake and tier words) the API never sends.
- Approvals: the panel form names agents for a person; over the API only a team devices session may.
- An agent's tier applies its proposals at once only on `workspace.changes.propose` with its key; through an OAuth connection or the task route they stay proposals.
- Every call of an agent's own is HTTP only: an agent reaching the site over MCP cannot claim, answer, fail, decide or set its wake.
