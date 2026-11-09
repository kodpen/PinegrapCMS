---
name: agent-channel
description: Plan (owner 2026-11-06, wake doors 2026-11-07): the Workspace channel from which the owner runs coordinators and agents: registry with @handles, wake rules and doors, scheduled wakes, queue, memory
---

## What is it for?
- One channel from which the owner runs the coordinators and agents: a message wakes an agent, it works and answers there, or a schedule set in the channel wakes it. GitHub stays the record of work; the channel is the conversation and the wake source (owner, 2026-11-07).

## What is built, and where is it described?
- Registry, membership, the agent card and the brakes: `workspace/agent-registry.md`; requests, wake rules and doors: `workspace/agent-doors.md`; scheduled wakes and the brief: `workspace/agent-wakes.md`; how an agent shows: `workspace/agent-shown.md`; memory and decision marks: `workspace/channel-memory.md`; asking people: `workspace/agent-questions.md`; model assistants: `workspace/model-assistants.md`; risk tiers: `workspace/agent-risk-tiers.md`; flows: `workspace/agent-flows.md`; the canvas: `workspace/agent-canvas-svg.md`.
- Agents use the site's MCP server, the API's routes under their scopes, no second catalogue (`api/api-mcp-server.md`). The decisions behind it: `workflow/decisions-made-workspace-7.md` to `-9.md`.

## What is left?
- A8, further doors: `agent-channel-steps-2.md`.

- The coordinators move their check-in chains to the channel: a coordinator is a member like any agent (`@koordinator-a`, kind `claude`, door push or poll), the owner directs it from the channel, and its wake comes from the site's cron, which is always on, while a session's own chain breaks on restart. It moves once the scheduled wakes are proven live (`workspace/agent-wakes.md` lists what was not tried); until then the chain stays as `workflow/coordinator.md` says.

- Beside it: `agent-channel-flag.md` (step AC), `agent-channel-questions.md` (step AQ).

## Who holds it, where does it start?
- One coordinator and its workers; a free slot takes the agent channel's free step before other plans' (owner, 2026-11-07), and design templates are low priority (`workflow/coordinator-no-plans.md`). Claim as `[plan:agent-channel] A<n> — <topic>`; the steps in `agent-channel-steps-2.md` (also what the plan took over from `workspace-agent-handles` and `claude-code-channel`).
