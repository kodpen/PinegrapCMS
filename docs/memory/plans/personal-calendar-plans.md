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
- PC1 (S, done #9474) Census: `workspace/personal-calendar-census-*.md` (readers, tables, words, mail, privacy); the architect answered the rest; the tables: `workflow/decisions-made-schema-18.md`.
- PC2 (M) Done #9477: "My calendar" (`workspace_my_calendar.php`) by month, week and list; the button on the E-mails toolbar and the Workspace menu tile (`workspace/workspace-my-calendar.md`).
- PC3 (M, schema) Plans: Done #9500 (`workspace/workspace-personal-plans.md`): step 11.0.201, the gate `ws_personal_plan_can_see()` and its guard test, the "New plan" drawer, My calendar and Today, the reminder line `plan_reminder`.
- PC4 (S) Done #9512: "Who can see" (Only me, Chosen team members, The whole team); the others read it under the owner's name and change nothing (`workspace/workspace-personal-plan-sharing.md`).
- PC5 (M) Roadmap: a plan with dated steps (title, day, done), drawn as a bar with its steps and its progress. "Make a task" on a step opens the task drawer filled; the step then follows the task's state.
- PC6 (M) For a customer: a plan names a contact (the pick of `workspace/workspace-visit-contact.md`) and shows on the customer's card and history to those who may see the plan. "New roadmap" on the card starts empty or from the person's own plans, copied. Merge and erasure as visits. PC6 also lists `ws_personal_plans.contact_id` in `pg_contact_holders()` and drops its test exception.
- PC7 (M) Done #9528: "Add to my calendar" in a mail's menu and the pane makes a plan tied to the conversation; an invitation (`ws_mail_items.invitation`, 11.0.205) is drawn as a card with the same button. No answer goes to the organiser (`workspace/workspace-personal-plan-mail.md`).
- PC8 (M) API and MCP: a person's own plans listed, added and changed by that person's connection under a scope of its own. An application key and a channel agent never read a private plan; an assistant hears of a plan only when its owner asks.
- PC3c (S, later) The board's Turkish name becomes "Planlama panosu" everywhere, with `tools/tr_add.php --replace` (architect 2857).
- PC9 (S) Close-out: the memory files, this plan deleted. Later: plans written into the person's Google or Outlook calendar. A roadmap is not shown to the customer on the site (owner, 2026-11-14).

## What does every step keep?
- Private is the rule: only its owner and those it names see a plan; no administrator, search, other person's export or assistant shows it.
- The Work Calendar, the planning board and capacity do not change: a plan takes no capacity.
- Team accounts only; a guest has no calendar.
- Look A (`ui/look-design-examples.md`), the whole picture set in the agent channel at once. Texts through `lang()`.
- No table or column without asking the architect.
- PC2 beside PC1; PC3 after PC1; PC4, PC5 and PC7 after PC3; PC6 after PC5; PC8 after PC4. Claim as `[plan:personal-calendar-plans] <step> — <topic>`.
