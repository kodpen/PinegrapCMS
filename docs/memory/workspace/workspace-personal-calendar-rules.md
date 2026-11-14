---
name: workspace-personal-calendar-rules
description: My calendar and a person's own plans (PC1-PC9, closed 2026-11-14): what the owner asked, the rules every change keeps, where each part is written up, what comes next
---

## What did the owner ask?
- Owner (2026-11-14): the calendar stays the Workspace's; the e-mail area gets a shortcut to a screen of the person's own calendar. A person adds plans, closed to others unless they allow it: a reminder, or a roadmap for a job that a customer's roadmap may be built from. His words: `workflow/decisions-made-workspace-27.md`; the tables: `workflow/decisions-made-schema-18.md`, `-19.md`.
- Owner (2026-11-14): a roadmap is not shown to the customer on the site. It stays on the team's side (the customer's card and history, to those who may see the plan).

## Which rules does every change keep?
- Private is the rule: only its owner and those it names see a plan; no administrator, search, Site Log, webhook, other person's export or assistant shows it. The one gate is `ws_personal_plan_can_see()`.
- A plan is never a `ws_events` row: many readers of `ws_events` never ask whose row it is (briefing, reminders, the outgoing subscription).
- The Work Calendar, the planning board and capacity do not change: a plan takes no capacity.
- Team accounts only; a guest has no calendar.
- No table or column without asking the architect.

## Where is each part written up?
- The census and its answers: `personal-calendar-census-*.md` (readers, tables, words, mail, privacy).
- The screen: `workspace-my-calendar.md`. Plans, the gate, reminders: `workspace-personal-plans.md`. Who can see: `workspace-personal-plan-sharing.md`. Roadmap: `workspace-personal-plan-roadmap.md`. A customer's roadmaps: `workspace-personal-plan-customer.md`. From a mail: `workspace-personal-plan-mail.md`. API and MCP: `workspace-personal-plans-api.md`. The board's Turkish name: `plan-board-grid.md`.

## What comes next?
- `plans/personal-calendar-more.md`: a contact's export of whole-team plans (PC6b), repeating plans, team roadmap templates, writing plans into the person's Google or Outlook calendar (PC13).
- Architect (3215), not yet a step: a roadmap drawn as one bar per week row, not a chip and "2/5" each day.
