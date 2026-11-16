---
name: api-panel-coverage-channels
description: The API beside the panel for channels and the agent channel (compared 2026-11-15, #9764, #9761; CA2-CA16 by 2026-11-16): each covered part and its topic file
---

## What was compared, and when?
- Owner (2026-11-15, agent channel message 5039): the channels' and the agent channel's API side before panel look work. Two comparisons on 2026-11-15 set every channel and agent action of the panel beside `/workspace/` routes and MCP tools: a channel's own side (#9764) and its agents (#9761). 40 gaps; each is a route now, or stays without one on purpose.
- Not routes: `channel-api-not-built.md`. Routes that still differ from the panel: `channel-api-differences.md`.

## Which agent-channel parts are covered?
- The "Add an agent" list and the agent card: `workspace-channel-agents-api-reads.md` (#9766).
- The person card, `seen` for a manager: `workspace/workspace-api-person-card.md` (#9771).
- A person sets an agent's wake (person only): `workspace-channel-agent-wake-api.md` (#9798).
- `agent:<id>`, `model:<id>` on the assistant requests: `workspace-assistant-requests-agents.md` (#9770).
- The words an agent reads on its own routes: `workspace/agent-route-words.md` (#9768).
- A task handed to an agent: `workspace/agent-tasks.md` (#9792).

## Which parts of a channel's own side are covered?
- Forward into a note, who has seen and acknowledged, bring the mentioned people in: `workspace-api-message-people.md`, `workspace/workspace-api-forward.md` (#9767).
- A file of the file manager, an edited file in place of the old, a file's history: `workspace-message-file-api.md` (#9769).
- A scheduled message changed, a scheduled reply, send now: `workspace-api-scheduled-message-changes.md` (#9786).
- Conclude and rename a discussion, take back or close an approval: `workspace-thread-approval-api.md` (#9794).
- Status update, check-ins started and answered: `workspace-api-status-checkins.md` (#9825).
- One channel's detail, Files and Board tabs, versions, export, `channel_id` on tables: `workspace-channel-reads-api.md` (#9790).
- Records watch, summary, the Claude, Pinegrap AI and voice switches: `workspace/workspace-api-channel-settings.md` (#9791).
- Private or public, join, clear, a version's name (person only): `workspace/workspace-api-channel-access.md` (#9800).
- A canvas's older version back, undo of an agent's edit, a page's tables: `workspace-canvas-versions-api.md` (#9829).
- A binned row deleted for good, a flow from a table, saved blocks, customer follow-up: `workspace/workspace-api-bin-flow-blocks-followup.md` (#9831).

## How is a new part added?
- As any panel route (`api-panel-coverage.md`, "How is a write route for a panel screen added?"), with a line here pointing at its topic file; a part left without a route gets its reason in `channel-api-not-built.md`.

Related: docs/memory/api/api-panel-coverage.md
