---
name: agent-work-apart-features
description: Features W1 to W12 of plan agent-work-apart: an agent channel's tasks left out of the general task lists and counts, the toggle, the mark of a task an application created, its filter, the inventory
---

## Which lists leave an agent channel's tasks out?
- W1 My Tasks (every scope), the Planning Board and the Work Calendar leave out the tasks whose channel is an agent channel, as the Decisions screen leaves out its decisions. One rule, `ws_agent_channel_filter()`, never a second test; before the upgrade (`ws_agent_channel_ready()` false) nothing is left out.
- W2 Each of the three gets the "Agent channels" toggle in the words and place the Decisions screen has it: on, the list shows the agent channels' tasks alone. It shows only when the reader may read an agent channel, and it is kept in the address as there (`agents=1`).
- W3 A task that names the reader in person stays where it reaches them: a task assigned to the reader shows in My Tasks "Mine" whatever its channel, with the channel's name on its row. A task in no channel is no agent channel's task.
- W4 `GET /workspace/tasks` leaves them out unless `agent_channels=include|only` or `channel_id` names the channel, as `GET /workspace/decisions` does; the schema names the parameter. An agent's own reads of its channel are unchanged.

## Which counts follow?
- W5 Today's task lines and count, the dashboard's task figures and the Workspace list's task sums count as the lists of W1 do, so a number never names tasks its list hides. The channel's own greeting and Tasks tab count as before.
- W6 Notifications are not filtered: a bell line, a mention or a hand-over addressed to a person arrives whatever the channel.

## How is a task an application created marked?
- W7 One column on `ws_tasks` holds the creating application's id, 0 for a person. Every route through which an application creates a task writes it: the API's task create with a key or an OAuth connection, a checklist item or message made into a task by an application, a flow run started by one, an assistant's task tool. The worker lists the routes it found in the PR body.
- W8 The task row, the board card and the drawer show "Made by <application's name>" beside the creator, never the owner's name alone; a deleted application reads "Made by an application".
- W9 My Tasks gets a "Made by agents" filter with three states: all, without them, them alone. "Created by me" no longer lists a task an application created with the reader's key unless the filter asks.
- W10 The task object of the API carries the application (`created_by_app`: id and name, or null); `GET /workspace/tasks` takes `made_by=people|apps`. Read with a ready check: before the upgrade every task reads as a person's and the filter is hidden.

## What does the inventory cover?
- W11 AW4 lists every other general reader of channels (search, the command palette, the weekly summary, exports, the customer's history, the calendar write) with what it does for an agent channel's tasks and decisions, and fixes the ones that mix. What it leaves and why goes into `workspace/agent-channel-flag.md`.
- W12 Tests in `tests/`: the filter over tasks (left out, included, alone, the named channel, the reader's own task), the counts, the mark's writer per route, the API parameters.
