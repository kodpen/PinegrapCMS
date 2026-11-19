---
name: agent-channel-readers
description: Which general readers leave agent channels' tasks and talk out (My Tasks, Planning Board, Work Calendar, dashboard and scheduled counts) and which stay whole, and why
---

## Why are an agent channel's tasks kept apart?
- Owner (2026-11-16, poll 80, `workflow/decisions-made-workspace-36.md`): agents' tasks piled up in the general task lists among the team's, as their decisions did. The switch of `agent-channel-flag.md` is widened, no second concept (plan agent-work-apart, #9941, #9960, #9994, #10473).

## Which rule do the task readers use?
- `ws_tasks_agent_rule()` in `includes/workspace/tasks.php` turns `ws_agent_channel_filter()` into the rule (agent channels and the discussions under them); `ws_tasks_agent_sql()`, `ws_tasks_agent_keeps()` and `ws_tasks_agent_out_sql()` apply it. Empty before the upgrade, so nothing is left out then.
- Tests: `tests/workspace_task_agent_channels_test.php`, `tests/workspace_agent_work_inventory_test.php`.

## Which screens leave them out?
- My Tasks (`ws_tasks_list()` with `agent_channels`), the Planning Board (`ws_board()`, `ws_plan_grid()`) and the Work Calendar (`ws_calendar()`): each has the "Agent channels" toggle (`agents=1` in the address), shown once the reader may read an agent channel. `GET /workspace/tasks` and `GET /workspace/plan` take `agent_channels` as the decisions route does.
- The Work Calendar's Conversations layer follows its tasks (`ws_calendar_channels_kept()`): the channels opened, the day's talk, the month's message figure, the busiest day and Most active channels leave agent channels out, and show them alone with the toggle.
- "Mine" keeps a task assigned to the reader whatever its channel; its row names the agent channel.

## Which counts leave them out?
- The dashboard's Team card and the Pipeline card's tasks box (#9960), and the panel dashboard's "The team closed N tasks this week" (`ws_dashboard_signals()` team_done_week, `ws_tasks_agent_count_sql()`).
- The scheduled counts (Open tasks, Overdue tasks, Tasks due today, Tasks done today, Time spent on tasks this week) with no channel chosen: `ws_scheduled_metric_channel_sql()`, `ws_task_time_week_narrow()` (minutes of a deleted task still count). A count of a chosen channel counts it, agent channel or not.
- Counted whatever the channel: the reader's own figures (Today, the My tasks and Week cards, "You closed N tasks"), widgets 27 and 29, the channel's own greeting and Tasks tab.

## Which readers stay whole, and why?
- Search and the command palette: a person looking for one item must find it; each hit is still checked with `ws_can_read_channel()`.
- A channel's own readers: its Tasks and Decisions tabs, board, brief, status facts, recap, export and the customer's history (a customer channel is never an agent channel's work).
- The person's own tasks: the task e-mail of scheduled actions (mode mine, or its chosen channel), the calendar feed and the calendar write to Google or Outlook (`workspace-calendar-write.md`) carry only tasks assigned to that person.
- Notifications: a bell line, a mention or a hand-over reaches its person whatever the channel. The assistants' tools and an agent's reads of its own channel are unchanged.
