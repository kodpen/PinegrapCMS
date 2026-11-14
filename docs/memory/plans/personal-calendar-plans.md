---
name: personal-calendar-plans
description: Plan (owner 2026-11-14): "My calendar" beside e-mail and a person's own plans, private unless shared, as a reminder or a job's roadmap, usable for a customer; steps PC1 to PC9
---

## What is asked?
- Owner (2026-11-14, in the architect's window): the calendar stays the Workspace's; the e-mail area gets a shortcut to a screen of the person's own calendar. A person adds plans, closed to others unless they allow it: a reminder, or a roadmap for a job that a customer's roadmap may be built from. His words: `workflow/decisions-made-workspace-27.md`. The mailbox half: `plans/mail-inbox-features.md`.

## What is there today?
- The Work Calendar (`ws_calendar()`) draws the team's tasks and plan items (`ws_events`), seen by the team; on the reader's own view also their bookings and own iCal layer (`workspace/workspace-personal-ical.md`).
- Many readers of `ws_events` never ask whose row it is (briefing, reminders, the outgoing subscription). A private row there would leak, so a plan is never a `ws_events` row.
- A task has a due time and a reminder (`workspace/workspace-task-reminders.md`) but is the team's, never private.

## Which steps?
- PC1 (S, no product code) Census, into this file: every reader of the calendar and of Today and what each does with a private row; the tables proposed (a plan, its steps, who sees it), each asked first; the words beside "plan item" and the planning board; whether `pg_mail_parse()` keeps a mail's calendar part; personal data export and erasure; an account that leaves.
- PC2 (M) "My calendar": the Work Calendar opened on the reader alone (own tasks, the plan items they are in, bookings, own iCal layer), by month, week and as a list; a "My calendar" button in the E-mails toolbar and the Workspace menu.
- PC3 (M, schema) Plans: the tables; `ws_plan_can_see()` as the one gate and a guard test that no reader goes around it; the "New plan" drawer (title, day or days, time, note, reminder); drawn for its owner on My calendar and Today; the reminder through the task reminders' path.
- PC4 (S) Sharing: "Who can see" on the drawer: Only me (the default), chosen team members, the whole team. The others read it on their My calendar under the owner's name and change nothing.
- PC5 (M) Roadmap: a plan with dated steps (title, day, done), drawn as a bar with its steps and its progress. "Make a task" on a step opens the task drawer filled; the step then follows the task's state.
- PC6 (M) For a customer: a plan names a contact (the pick of `workspace/workspace-visit-contact.md`) and shows on the customer's card and history to those who may see the plan. "New roadmap" on the card starts empty or from the person's own plans, copied. Merge and erasure as visits.
- PC7 (M) E-mail and calendar: "Add to my calendar" in a mail's menu makes a plan tied to the conversation; an invitation in a mail is drawn as a card with the same button. No answer goes to the organiser.
- PC8 (M) API and MCP: a person's own plans listed, added and changed by that person's connection under a scope of its own. An application key and a channel agent never read a private plan; an assistant hears of a plan only when its owner asks.
- PC9 (S) Close-out: the memory files, this plan deleted. Later: plans written into the person's Google or Outlook calendar. A roadmap is not shown to the customer on the site (owner, 2026-11-14).

## What does every step keep?
- Private is the rule: only its owner and those it names see a plan; no administrator, search, other person's export or assistant shows it.
- The Work Calendar, the planning board and capacity do not change: a plan takes no capacity.
- Team accounts only; a guest has no calendar.
- Look A (`ui/look-design-examples.md`), the whole picture set in the agent channel at once. Texts through `lang()`.
- No table or column without asking the architect.
- PC2 beside PC1; PC3 after the owner's yes to the tables; PC4, PC5 and PC7 after PC3; PC6 after PC5; PC8 after PC4. Claim as `[plan:personal-calendar-plans] <step> — <topic>`.
