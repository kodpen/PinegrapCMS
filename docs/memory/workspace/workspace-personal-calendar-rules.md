---
name: workspace-personal-calendar-rules
description: My calendar and a person's own plans (two plans, both closed 2026-11-15): what the owner asked, the rules every change keeps, where each part is written up, what is still open
---

## What did the owner ask?
- Owner (2026-11-14): the calendar stays the Workspace's; the e-mail area gets a shortcut to a screen of the person's own calendar. A person adds plans, closed to others unless they allow it: a reminder, or a roadmap for a job that a customer's roadmap may be built from. His words: `workflow/decisions-made-workspace-27.md`; the tables: `workflow/decisions-made-schema-18.md`, `-19.md`.
- Owner (2026-11-14): a roadmap is not shown to the customer on the site. It stays on the team's side (the customer's card and history, to those who may see the plan).
- Owner (2026-11-14, polls 3359 and 3363): then a contact's export of whole-team plans, repeating plans, team roadmap templates and plans written into the person's Google or Outlook calendar (`workflow/decisions-made-workspace-29.md`, `-30.md`; the tables: `decisions-made-schema-20.md`).

## Which rules does every change keep?
- Private is the rule: only its owner and those it names see a plan; no administrator, search, Site Log, webhook, other person's export or assistant shows it. The one gate is `ws_personal_plan_can_see()`.
- A plan is never a `ws_events` row: many readers of `ws_events` never ask whose row it is (briefing, reminders, the outgoing subscription).
- The Work Calendar, the planning board and capacity do not change: a plan takes no capacity.
- Team accounts only; a guest has no calendar.
- No table or column without asking the architect.
- A team roadmap template holds no customer and no person's name beyond its maker's; it is never a plan row, so no plan reader or the gate sees it.

## Where is each part written up?
- The census and its answers: `personal-calendar-census-*.md` (readers, tables, words, mail, privacy).
- The screen: `workspace-my-calendar.md`. Plans, the gate, reminders: `workspace-personal-plans.md`. Who can see: `workspace-personal-plan-sharing.md`. Roadmap: `workspace-personal-plan-roadmap.md`. A customer's roadmaps: `workspace-personal-plan-customer.md`.
- A contact's export: `workspace-personal-plan-export.md`. From a mail: `workspace-personal-plan-mail.md`. Repeating plans: `workspace-personal-plan-repeat.md`. Team templates: `workspace-roadmap-templates.md`. Written to Google or Outlook: `workspace-calendar-write-plans.md`. API and MCP: `workspace-personal-plans-api.md`. The board's Turkish name: `plan-board-grid.md`.

## What is still open?
- No plan is open. Architect (3215), not yet a step: a roadmap drawn as one bar per week row, not a chip and "2/5" each day.
