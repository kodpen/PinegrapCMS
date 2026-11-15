---
name: workspace-personal-overview
description: A person's own calendar layers as a whole: own iCal calendars and own plans on My calendar; the rules that bind them and which file holds each part
---

## What is it?
- What a team member keeps for themselves beside the team's Work Calendar: their own iCal calendars, drawn read-only (`workspace-personal-ical.md`, `-ical-days.md`), and their own plans on My calendar (`workspace-personal-plans.md` and the files below).
- The rules every change keeps, what the owner asked and what is still open: `workspace-personal-calendar-rules.md`. No plan is open.

## Which rules bind it?
- Private is the rule: a plan is read through one gate, `ws_personal_plan_can_see()`; no administrator, search, Site Log or assistant shows a plan to anyone else.
- A plan is never a `ws_events` row, takes no capacity and changes neither the Work Calendar nor the planning board. Team accounts only.
- Only the plan modules name the plan tables (`tests/workspace_personal_plans_test.php`); a template is kept in its own tables, never as a plan.

## Which file holds which part?
- Plans, the gate, the drawer, reminders, account deletion: `workspace-personal-plans.md`.
- "Who can see" (Only me, Chosen team members, The whole team): `workspace-personal-plan-sharing.md`.
- Roadmap (dated steps, the bar, "Make a task"): `workspace-personal-plan-roadmap.md`.
- A plan made for a customer (the card's Roadmaps, "New roadmap", history, merge and erasure): `workspace-personal-plan-customer.md`.
- A contact's data export of whole-team plans: `workspace-personal-plan-export.md`.
- "Add to my calendar" on a mail and invitations: `workspace-personal-plan-mail.md`.
- Repeating plans (Repeat, This one / This and the following, a reminder each time): `workspace-personal-plan-repeat.md`.
- Team roadmap templates ("Save as team template", "Team templates" under "New roadmap", "Move the following steps too"): `workspace-roadmap-templates.md`.
- Own plans written one way to Google or Outlook: `workspace-calendar-write-plans.md`.
- The external API and MCP: `workspace-personal-plans-api.md`.
- Own iCal calendars: `workspace-personal-ical.md`, which days they touch: `workspace-personal-ical-days.md`.

## How is a new part added?
- A new reader of plans goes through the gate and into one of the plan modules (`includes/workspace/personal_plan*.php`); a new memory file joins this list; a new table or column is asked of the architect first.
