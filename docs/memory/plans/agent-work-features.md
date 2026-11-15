---
name: agent-work-features
description: Plan (owner 2026-11-15, agent channel poll 49): an agent's duty text, its progress note, a task handed to a registry agent, Right or Wrong on an agent's answer; steps AF1 to AF5b
priority: 6
---

## What is asked?
- The R&D agent proposed four features (agent channel messages 5084 to 5087 hold the detail and what exists today). The owner chose all four in poll 49: `workflow/decisions-made-workspace-33.md`; the column and the table: `workflow/decisions-made-schema-21.md`.
- The order he was told: duty text and progress note first, the task hand-over next, Right or Wrong last. Claim as `[plan:agent-work-features] <step> — <topic>`; given 2026-11-15 (agent channel message 5131).

## Which steps are there?
- AF1 (S) The duty text. After AS2 #9759 (the same form), `@elcin`.
  - A migration step adds `ws_agents.duty` (text) with the idempotent helper; a Duty field of about 2,000 characters on the agent's form; a "Your duty" part in `ws_agent_instructions_text()`, whose version changes with it; the agent card shows it to whoever may read the channel.
  - The column is `ws_agents.duty TEXT NULL`, step 11.0.217; a site whose update has not run works as today. The field's label is "Görev tanımı" (the architect's call). On the card a cut duty opens whole on a click or tap.
- AF2 (S) The progress note. Done (#9782, `workspace/agent-progress-note.md`).
  - `POST /workspace/agent-requests/{id}/progress`: a text of at most 200 characters, only the key of the agent that holds the request, off MCP, one write in 30 seconds at most. Kept in the request's `ai_state`, cleared when the request closes.
  - `claudeStateLine()` shows it after "is working on it" with "since HH:MM"; the agent's instructions name the route. No message, no wake, no inbox line.
- AF3 (S) after AF1: the instructions name the other agents of each of the agent's channels with their role and the duty's first line.
- AF4a (M) A task handed to a registry agent, the server side. Done (#9792, `workspace/agent-tasks.md`).
  - An application or model agent allowed in a channel takes that channel's tasks (`ws_task_delegate()` with `agent:<id>` or `model:<id>`, no new column). Only a person hands over; a key hands no task over, as today.
  - The hand-over makes the agent's request with the task in its brief; answer and fail write the task note; Stop as for Claude.
- AF4b (S to M) after AF4a: the task drawer offers the channel's agents beside the assistants; the Tasks tab and the Board show which agent holds what.
- AF5a (M) after AF4a: Right or Wrong, the record. Done (#9814, `workspace/agent-feedback.md`). A small table, one row per marked reply (verdict, a reason of at most 300 characters, who, when); marked by the asker and the channel's managers; `GET /workspace/agent-feedback` for the agent's own key; the counts of the last 7 and 30 days.
- AF5b (S) after AF5a: the two buttons under an agent's reply, the reason on Wrong, the counts on the Agents card and the agent card.
- Last (S): close-out: the topic files in `workspace/`, this plan deleted.

## What is left out?
- A task open to a role and claimed by the first agent; an agent handing a task on with its own key; a Wrong that wakes the agent to answer again. Each is a later yes of the owner.

## Whose look?
- Workspace is a kept screen: AF2, AF4b and AF5b post close-up pairs in the agent channel and the architect brings them to the owner; none is marked ready before his look. AF1's form field is the architect's look.
- AF2's look is given: Owner (2026-11-15, agent channel poll 53): "Evet, böyle çıksın" (`workflow/decisions-made-workspace-34.md`).
