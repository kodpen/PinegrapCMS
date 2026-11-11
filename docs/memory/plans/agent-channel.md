---
name: agent-channel
description: Plan (owner 2026-11-06, wake doors 2026-11-07): the Workspace channel from which the owner runs coordinators and agents: registry with @handles, wake rules and doors, scheduled wakes, queue, memory
---

## What is it for?
- One channel from which the owner runs the coordinators and agents: a message wakes an agent, it works and answers there, or a schedule set in the channel wakes it. GitHub stays the record of work; the channel is the conversation and the wake source (owner, 2026-11-07).

## What is built, and where is it described?
- Registry, membership, the agent card and the brakes: `workspace/agent-registry.md`; requests, wake rules and doors: `workspace/agent-doors.md`; scheduled wakes and the brief: `workspace/agent-wakes.md`; how an agent shows: `workspace/agent-shown.md`; memory and decision marks: `workspace/channel-memory.md`; asking people: `workspace/agent-questions.md`; model assistants: `workspace/model-assistants.md`; risk tiers: `workspace/agent-risk-tiers.md`; flows: `workspace/agent-flows.md`; the canvas: `workspace/agent-canvas-svg.md`.
- Agents use the site's MCP server, the API's routes under their scopes, no second catalogue (`api/api-mcp-server.md`). The decisions behind it: `workflow/decisions-made-workspace-7.md` to `-10.md`.

## What is left?
- A8, the Claude Code channel plugin: it waits for its repository, which the owner opens (`agent-channel-steps-2.md`). The rest of A8 is built.

- The coordinators move their check-in chains to the channel: a coordinator is a member like any agent (`@koordinator-a`, an `app` agent with an application of its own, door push or poll: the registry keeps one `claude` row, and scheduled wakes serve `app` agents only), the owner directs it from the channel, and its wake comes from the site's cron, which is always on, while a session's own chain breaks on restart.
  - The scheduled wakes are proven live on both sides: the cron tick makes the request (dev, 2026-11-10), and an agent read a wake's request and brief with its key and answered it with a text (2026-11-11, `workspace/agent-key-trial-2.md`).
  - What the move still needs: the owner registers each coordinator as an application and an agent on the site and puts its key into that coordinator's environment, and `workflow/coordinator.md` is changed by a PR that gives its reason. Until then the chain stays as that file says.

- The live trial on dev (owner, 2026-11-09, `workflow/decisions-made-workspace-10.md`): the architect's application is registered as `@mimar` with a poll door and added to the agent channel (wake on a mention, tier 1). The mention and `/wake` were tried on 2026-11-10 (`workspace/agent-doors.md`, `agent-wakes.md`). The agent's side was tried the same day with the application's key (`workspace/agent-key-trial.md`).
  - The second day (2026-11-11): `workspace/agent-key-trial-2.md`. Left, each needing the owner's hand in the panel: `agent-channel-trial.md`; the trial's findings are all fixed.

- Beside it: step AC done (#8440: `workspace/agent-channel-flag.md`), step AQ done (#8438: `workspace/agent-checklist-questions.md`, `workflow/agent-channel-wake.md`).

## Who holds it, where does it start?
- One coordinator and its workers; a free slot takes the agent channel's free step before other plans' (owner, 2026-11-07), and design templates are low priority (`workflow/coordinator-no-plans.md`). Claim as `[plan:agent-channel] A<n> — <topic>`; the steps in `agent-channel-steps-2.md` (also what the plan took over from `workspace-agent-handles` and `claude-code-channel`).
