---
name: workspace-today-page
description: The Workspace's first page, Today (?view=home, was Overview): the greeting and its count line, the cards in the drawn order, what can be done from them, where each card's data comes from
---

## What is the page called, and where is it?
- "Today" ("Bugün"): the `home` place of `ws_nav_groups()` and `t('home')` in its header (#4196, owner #4192). The address stays `workspace.php?view=home` and every link to it works; the code keeps the name `home` (`showHome()`, `drawHome()`, `ws_home()` in includes/workspace/home.php).
- The header holds the name alone; the day stands over the greeting.

## What does it show, in which order?
- The greeting, only here: the day, "Good evening, Name" (`ws_home_greeting()`, by the hour), the count line, the ways to start (new channel, new private channel, a guest conversation where allowed).
- Three cards: Waiting for you, Today and overdue, Calendar · today. Then two: What happened in the channels, Latest decisions. Then Channels you could join and What is the Workspace? (open for a newcomer). Cards share one look (`.ws-home-card`) and sit in auto-fit grids; the two-column one is `minmax(min(22rem, 100%), 1fr)` so a 360 px phone does not scroll sideways (#4296).

## What does the count line say?
- `ws_home_lead()`: unread messages and mentions of the joined channels (the sidebar's counts), approval requests and read receipts waiting, tasks due today or before, each a part ("7 new messages", "1 mention" …), or "Everything is read and nothing is due today." The page joins the parts with dots and lets each wrap whole.

## What is in each card?
- Waiting for you: approvals (`ws_approvals_waiting()`) and read receipts (`ws_acks_waiting()`) drawn by their own files (`homeRows()` of workspace_approvals.js and workspace_acks.js: "Name asks for your approval"), then replies and mentions (`ws_home_talk()`), the unread marked; read ones only while the card has fewer than five rows. Its count, in the accent, is what is unread or asked; a sign sits in a box the size of a face so every row's text starts at one line.
- Today and overdue: the person's open tasks due today or before (as the my-day route reads them), else the ones coming up. `ws_home_due_pick()` keeps the ones due today first, fills the six with the earliest overdue, by due date; "N more task(s) · My Tasks" when the count (`tasks_due`) is larger (#4326, #4295). The priority badge sits beside the shortened title in `.ws-home-task-head`, never inside it (#4296). Under them open, overdue and pressing counts; pressing is a COUNT like `?urgent=1` (urgent/high, due within 7 days or overdue), not the five listed. The box ticks a task off (`ws_task_status` done), the rest of the row opens it.
- Calendar · today: `ws_home_calendar()` (plan items about the person, `ws_event_applies()`, and their own calendars' events), rows from `ws_home_calendar_rows()`: all-day first, then by the hour, at most eight; the one going on now marked with Join for an http(s) meeting link, the ones over dimmed.
- What happened in the channels: `ws_home_digest()`, joined channels with unread messages, the latest last message first (system lines neither show nor order), its count ("3 new", in the accent with a mention) and the last message.
- Latest decisions: the timeline's last five.

## Why?
- Owner (2026-10-31, `workflow/decisions-made-workspace-5.md`): the drawn design; the name Today because the page leads with today's greeting, tasks and calendar. The old count line "Waiting for you: 0" contradicted the card under it; the new one counts what is new.
- Tests: `tests/workspace_overview_test.php`.
