---
name: workspace-today-page
description: The Workspace's first page, Today (?view=home, was Overview): the greeting and its count line, the cards in the drawn order, what can be done from them, where each card's data comes from
---

## What is the page called, and where is it?
- "Today" ("Bugün"): the `home` place of `ws_nav_groups()` and `t('home')` in its header (#4196, owner #4192). The address stays `workspace.php?view=home` and every link to it works; the code keeps the name `home` (`showHome()`, `drawHome()`, `ws_home()` in includes/workspace/home.php).
- The header holds the name alone; the day stands over the greeting.
- The panel heading follows the view: `ws_channels_screen_headings()` (home.php), swapped by `screenHeading()` in workspace.js. Help stays `workspace#channels` (#5191).

## What does it show, in which order?
- In the new dashboard's design (`plans/workspace-today-dashboard.md`): `div.pg-dash.ws-today` in `.ws-home`, the person's dashboard face and calm (`ws_today_look_for()`), not backdrop or dense; workspace.php links pg_dash and ws_today stylesheets (`ws_today_stylesheets()`), not pg_dash.min.js.
- The greeting card, only here (`.pg-dash-greeting`): the day, "Good evening, Name" (`ws_home_greeting()`), the count line, the ways to start as `.pg-dash-btn` (new channel main, private channel, guest conversation where allowed).
- Then the `.pg-dash-grid`, fixed (no edit mode): Waiting for you, Today and overdue, Calendar · today (S), What happened in the channels (M), Latest decisions (S), Channels you could join (S, when any), What is the Workspace? (L, a `details`, open for a newcomer). One method each (`homeWaiting` … `homeAbout`), framed by `todayCard()`: icon, title, count badge, link.

## What does the count line say?
- `ws_home_lead()`: unread messages and mentions of the joined channels (the sidebar's counts), approvals and read receipts waiting, tasks due today or before, each a part ("7 new messages"), or "Everything is read and nothing is due today."; parts joined with dots, each wrapping whole. `ws_home_lead_capitalised()` upper-cases the line's first letter (the Turkish due part starts with a word, #5191).

## What is in each card?
- Waiting for you: approvals (`ws_approvals_waiting()`) and read receipts (`ws_acks_waiting()`) drawn by `homeRows()` of workspace_approvals.js and workspace_acks.js, then replies and mentions (`ws_home_talk()`), the unread marked; read ones only while the card has fewer than five rows. its count, in the accent, is what is unread or asked; a sign sits in a face-sized box so rows align.
- Today and overdue: the person's open tasks due today or before (as the my-day route reads them), else the ones coming up. `ws_home_due_pick()` keeps the ones due today first, fills the six with the earliest overdue, by due date; "N more task(s) · My Tasks" when the count (`tasks_due`) is larger (#4326, #4295). The priority badge sits beside the shortened title in `.ws-home-task-head`, never inside it (#4296). Under them open, overdue, pressing as `.pg-dash-chip`; pressing is a COUNT like `?urgent=1` (urgent/high, due within 7 days or overdue), not the five listed. The box ticks a task off (`ws_task_status` done), the rest of the row opens it.
- Calendar · today: `ws_home_calendar()` (plan items about the person, own calendars' events, confirmed bookings: `workspace-bookings.md`), rows from `ws_home_calendar_rows()`: all-day first, then by the hour, at most eight; the one going on now marked with Join for an http(s) meeting link, the ones over dimmed.
- What happened in the channels: `ws_home_digest()`, joined channels with unread messages, latest first (system lines ignored), its count ("3 new", in the accent with a mention) and the last message.
- Latest decisions: the timeline's last five.

## Why?
- Owner (2026-10-31, `workflow/decisions-made-workspace-5.md`): the drawn design; the name Today because the page leads with today's greeting, tasks and calendar.
- Tests: `tests/workspace_overview_test.php`, `tests/workspace_today_test.php`.
