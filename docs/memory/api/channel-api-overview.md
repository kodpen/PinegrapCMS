---
name: channel-api-overview
description: The channel API census files (plan channel-api-gaps, owner 2026-11-15): what the two censuses compare, the rules that bind a new route, and which file holds each part
---

## What do these files hold?
- Two censuses of the plan `plans/channel-api-gaps.md` (owner 2026-11-15, agent channel message 5039: channels and their API side before the panel's look). Each sets what a person does in the panel beside the `/workspace/` routes and MCP tools: the route that does it, or none.
- CA1a (#9764) covers a channel's own side; CA1b (#9761) the agents' side. Each ends in a gap list, a list of what has no route on purpose, and a list of what a route does differently from the panel.
- The architect cuts the route steps (CA2 and on) from the gap lists into the plan; its close-out corrects `api-panel-coverage.md` and keeps this overview current.

## Which rules bind a route built from them?
- What has no route on purpose stays so: Site Settings and the Agents card (`api-settings-no-endpoint.md`), a write the person must make themselves (`workspace/workspace-api-callers.md`), a guest's reach.
- A new route reuses the panel's write path (`api-panel-coverage.md`), has a dry run where it writes, its schema row and its tests; it gives an application no more than its scopes and channel membership give today.

## Which file holds what?
- A channel's own side, start file and totals: [[channel-api-census-channel]].
  - Messages, polls, threads, scheduled and forwarded: [[channel-api-census-channel-messages]].
  - The channel itself (make, rename, switches, versions, members, guests, tabs): [[channel-api-census-channel-itself]].
  - Decisions, memory, canvases, files, tables, the GitHub link: [[channel-api-census-channel-content]].
  - Gaps G1-G25: [[channel-api-census-channel-gaps]]; G26-G33: [[channel-api-census-channel-gaps-content]].
  - No route on purpose: [[channel-api-census-channel-no-route]]; a route that differs: [[channel-api-census-channel-differences]].
- The agents' side, start file and count: [[channel-api-census-agents]].
  - A channel's agents, model assistants, the agent and person cards: [[channel-api-census-agents-channel]].
  - An agent's own calls, questions, approvals, proposals: [[channel-api-census-agents-calls]].
  - What an agent meets finding its way with the API alone: [[channel-api-census-agents-wayfinding]].
  - Gaps: [[channel-api-census-agents-gaps]]; no route on purpose and a route that differs: [[channel-api-census-agents-differences]].

## How is a new part added?
- A census part or a gap list split in two gets its own `channel-api-census-<side>-<part>.md` and a line here and in its side's start file.

Related: docs/memory/api/api-panel-coverage.md
