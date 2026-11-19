---
name: agent-work-apart
description: Plan (owner 2026-11-16, agent channel poll 80): an agent channel's tasks kept apart from the general task lists as its decisions are, a task an application created marked so; steps AW1 to AW4
priority: 6
---

## What is asked?
- Owner (2026-11-16, to the architect): agent questions and tasks pile up in the Workspace's general Decisions and Tasks and mix with those of the team working on the same site. He asked for research, then chose "Evet, ikisi de; planını yaz" in poll 80 (`workflow/decisions-made-workspace-36.md`).
- What the research found on dev: 80 of the 104 decisions on the Decisions screen came from the development channel, 74 of them marked by applications. The agent channel switch (`workspace/agent-channel-flag.md`) keeps such a channel's decisions apart, but it was never switched on there, and only the decisions timeline reads it.
- A task does not record that an application created it: `ws_task_create()` takes the viewer alone, so a task made with an application's key reads as its owner's. 34 of the 46 tasks carried the owner's id, 20 of them test tasks, none in an agent channel.
- No second concept: the existing switch is widened. The features are `plans/agent-work-apart-features.md` (W1 to W12): a step's PR body names each feature it builds and where it is.

## Which steps are there?
- AW1 (M) The task lists, W1 to W4 (done, #9941): `ws_tasks_list()` and the readers of My Tasks, the Planning Board and the Work Calendar leave an agent channel's tasks out unless asked, through the one helper `ws_agent_channel_filter()`; the "Agent channels" toggle on each; `GET /workspace/tasks` with `agent_channels`. The architect's look.
- AW2 (S) after AW1 (done, #9960): the counts, W5 and W6: the dashboard's Team card and the Pipeline card's tasks box count without agent channels' tasks; a task assigned to the reader counts whatever its channel (W3).
- AW3 (M) after AW1 (done, #9994): the mark, W7 to W10. One column on `ws_tasks` for the application that created the task, written by every route an application creates a task through; "Made by <application>" on the task row, the board card and the drawer; the "Made by agents" filter; the API field. The owner's look.
- AW4 (S) after AW3 (done, #10473): the inventory, W11 and W12, with the Work Calendar's Conversations layer, its message figure and Most active channels, and the scheduled counts of includes/workspace/scheduled.php; close-out into `workspace/agent-channel-flag.md` and a new workspace file on the task's application mark; this plan deleted.

## Which schema change is there?
- One column on `ws_tasks`, the owner's yes in poll 80 (`workflow/decisions-made-schema-23.md`). AW3 states its name, type and index in the draft PR's body and asks the architect before the migration step is written. No backfill. Nothing else is added.

## What is not changed?
- The channel's own Tasks and Decisions tabs, its board and its brief; who may read a task.
- An agent question waiting for a person still reaches that person (the ring, Today, the Assistant card: `workspace/poll-agent-question-reach.md`).
- Earlier tasks get no mark. Test tasks already made are cleaned by whoever made them, not by a step.

## Whose look?
- AW1's toggle is the architect's (owner's channel message 2645): the toggle the Decisions screen has, in the same place and words.
- AW3's "Made by" line and filter are the owner's: new on kept screens. AW3 posts in the agent channel, at 1440 and 390, light and dark: a task row, a board card, the drawer, the filter on My Tasks. The architect brings one sheet to him; not marked ready before his look.
