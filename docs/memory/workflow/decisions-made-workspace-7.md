---
name: decisions-made-workspace-7
description: Decisions list, workspace part 7 (2026-11-06 on): the agent channel: an agent's own messages, how far an unattended flow goes, decision marks, the loop-guard text, agents woken from the channel
---

## Where are the rules and the earlier parts?
- `decisions-made.md`; its Parts line lists the files. Entries name the plan, the date and the reason. Plan: `plans/agent-channel.md`.

## How is an agent keyed, and what is a channel's memory? (owner, 2026-11-06)
- A key per agent: each registry row acts with its own application or its own sealed model key, never a shared one (`workspace/agent-registry.md`). A channel's memory is a list of entries (title, text, tags, author, pinned, expiry), not one free text (`workspace/channel-memory.md`).

## May an agent edit or delete its messages in an agent channel?
- Owner (2026-11-06): only its own, with an "edited" mark kept on the message; never another member's. Reason: an agent must be able to take a wrong answer back, and the mark keeps the trail.

## How far may an unattended flow act in an agent's name?
- Owner (2026-11-06): up to tier 3 (irreversible work: cancelling an order, a refund, a deletion, money) where the channel's manager allowed that tier to that agent; tier 1 (reading, messages) and tier 2 (reversible record changes: tasks, notes, proposals) by the agent's permission row. Reason: full automation is the channel manager's choice, not a product ceiling.
- Architect: every tier above 1 is off by default per agent and channel; a tier-2 or tier-3 change writes the undo journal where the kind can be undone; the depth and hourly brakes hold for every tier; the permission relay never runs unattended.

## Which message counts as a decision?
- Owner (2026-11-06): any member, a person or an agent, may mark a message as a decision (the owner's answer, or a decision the agents reached among themselves). A marked message from the owner's own account is an owner decision: the architect records it in the decisions list as it records an answer given in its window. A marked message of anyone else is a channel decision: it goes into the channel's memory and its brief, never into the decisions list. Reason: the owner answers in the Workspace too, and the agents need a line they can quote.

## Is there a text against loops?
- Owner (2026-11-06): every brief carries a very short general text that tells the agent when not to answer and to check in at set intervals (an answer only when the message needs it, no answer to an answer unless asked, a pause after a run of exchanges); the channel's owner may switch it off for the channel. Reason: short, visible to the agent, cheap.
- Architect: the text is guidance, never a security layer: the depth and hourly brakes stay in code whether the text is on or off (`agent-channel.md`, A3 and A4).

## How are coordinators and agents reached from the channel?
- Owner (2026-11-07): the coordinators and the other agents must be wakeable from the channel, or able to set up their own scheduled wake there: a message to an agent knocks its door (a push to its URL, or its poll), and an agent asks the channel for `every N minutes` or `at <time>` wakes the site's cron delivers. Reason: the owner runs the coordinators and agents from one place, and a session's own timer chain breaks when the session restarts. Plan: `plans/agent-channel.md` (A3, A4).

## Through what do agents use the channel's tools?
- Owner (2026-11-07): through the site's MCP server, as the API's routes; no separate tools API. The canvas becomes a tool too (the agent writes SVG: `decisions-made-workspace-9.md`), the decision mark and the channel memory get routes; workflow tables, polls, approvals, notes, files and tasks already have theirs. Reason: one catalogue, one scope screen, one rate limit. Plans: `plans/agent-channel.md` (A4, A5); the canvas: `workspace/agent-canvas-svg.md`.
