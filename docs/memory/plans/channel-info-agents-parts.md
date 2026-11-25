---
name: channel-info-agents-parts
description: Plan channel-info-agents, step CI1: every part Channel info's Info and Activity tabs have today and where each stands after the agents block (stays, moves, goes)
priority: 5
---

## Where does the block stand?
- With the tabs (`activityTabsShow()`) and at least one row: the first part of the pane that is shown. It is one node: `show()` moves it to the top of the other pane, never draws it again, so open rows and "n more" keep their state. Each pane keeps its own scroll.
- With no tabs (assistants only): `drawInfo()` puts it where "Agents here" is today, after People.
- With no row at all (a repository link only): no block, nothing in its place.

## Info's parts, in today's order?
- The tabs row: stays.
- "Purpose" (the topic, the customer, the department): stays, now under the block.
- "Status" (the status pill): stays, after Purpose.
- "People here · n" with its rows and "Add people": stays.
- "Agents here · n" (`agentInfoRow()` rows in `.ws-info-agents`): goes from `drawInfo()`; the block replaces it.
- "Sharing", "Notifications", the time part, the manager's "Channel settings" button at the foot, "The conversation": stay, in today's order.
- The A1 board draws "Manage" under today's list from an earlier build; the code has none. The block's icon button is the only way from the agents to their settings.

## The Activity pane's parts, in today's order?
- The summary line ("3 ready", `.ws-act-summary-line`): goes; the head of the block says it, and only for the states that ask for a look.
- "Project: …" (`.ws-act-project`, with its icon): stays, the first line under the block.
- "Agents here · n" (`drawAgents()`, `.ws-act-agent` rows): goes; the block replaces it.
- "Requests", "Waiting for a person", "Decisions today", "Repository", "Plan usage" and its tip: stay, in today's order.
- The loading line and "The activity could not be read.": stay, under the block. The block itself stays drawn from what the channel already holds when the read fails.

## Where does each part of today's Info row go?
- Face: the row's face. Name: line 1; it opens no card in the block. Role: line 1, after the name.
- Badge "AI agent": goes. Badge "Assistant": becomes line 2's first word for an assistant. Badge "Paused": becomes line 2's word, the row dimmed.
- `@handle · <wake rule>`: the facts "Handle" and "Wakes" of the opened row.
- Last seen at the row's end: line 2 for an agent not seen; the fact "Seen" of the opened row for every agent.

## Where does each part of today's Activity row go?
- The state dot: stays on the face (`.ws-agent-dot`). The state pill: goes; its word is line 2's first word.
- "Asked by <name>" with its link and "since HH:MM": the fact "Asked by"; at rest "for <name>, since HH:MM" while there is no note.
- The progress note: line 2 at rest (one line, cut), whole in the fact "Doing".
- The context's words and bar: the ring at rest (the words in its tip); the fact "Context" with a bar.
- Model · branch · "reported …": the model is line 2 of a ready agent; the facts "Model", "Branch" and "Reported".
- The dimmed report of an agent that reported more than a day ago: "reported …" in amber on line 2 and in the fact.

## What stays as it is elsewhere?
- The discussion's people panel and its list (`agentInfoRow()`, `.ws-info-agents`, the badges "AI agent", "Following"): not touched.
- The agent card on messages and on the channel head's faces; the agent faces in the channel's head; "Agents in this channel" in Channel settings.
