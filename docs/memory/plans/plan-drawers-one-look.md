---
name: plan-drawers-one-look
description: Plan (owner 2026-11-16): the Planning Board's "Add to the plan" drawer and My calendar's "New plan" drawer look the same, built from one set of form parts; the stores stay apart; PD1 to PD3
priority: 6
---

## What is asked?
- Owner (2026-11-16, to the architect, with a picture of the "New plan" drawer): "evet planını yaz, iki çekmece aynı görünsün" and "ayrıca bu çekmecede bir sorun var. bileşenleri iyi ama modern".
- Before it he asked whether the two use one engine. They do not, by his decision of 2026-11-14 (`workspace/workspace-personal-calendar-rules.md`): a plan is never a `ws_events` row.

## What is the architect's reading, not his words?
- The model is the "New plan" drawer: its fields are look A's (`pg-input`, `pg-field-label`, `pg-switch`, `pg-seg`, `pg-button`), which he calls good and modern. "Add to the plan" is brought to it; it is Bootstrap's small form today.
- "A problem in this drawer" is what his picture shows: the body scrolls sideways and "Save" is cut. That is fixed by #9858, not by this plan.
- The owner sees both drawers side by side before PD2 lands, so a wrong reading costs one step.

## Which two drawers?
- "Add to the plan" (`add_plan_item`): `eventForm()` in `assets/js/workspace.js`, posts `ws_event_save` / `ws_event_delete`, table `ws_events`. Opened from the Planning Board (`startBoard()`), the Work Calendar (`startCalendar()`), a member's menu (`personMenu()`) and My calendar (`h.planItem`).
- "New plan" (`new_plan`): `planDrawer()` in `assets/js/workspace_my_calendar.js`, posts `ws_personal_plan_save` / `_delete`, tables `ws_personal_plans` and its two beside it.
- Both already open in the same shell, `offcanvas()` (`min(34rem, 100vw)`).

## What is built?
- The numbered features: `plan-drawers-one-look-features.md` (D1 to D14).
- Steps: `plan-drawers-one-look-steps.md`. Order: PD1 after #9858 landed; PD2 after PD1; PD3 last.
- Claim as `[plan:plan-drawers-one-look] <step> — <topic>`. A step's PR body lists its D numbers, each with the test or the picture that shows it.

## Whose look?
- Nothing changes on the screen in PD1: its before and after pictures are the same, the architect compares them.
- The owner's in PD2 (Workspace is a screen he keeps): one sheet with the two drawers side by side and one short question the architect brings him. It is not marked ready before his answer.

## What is left out?
- One store, one drawer or one engine for both; a button that turns one into the other; any column, table, action, API route or MCP tool.
- A new field in either drawer (Reminder or Repeat on a plan item, Kind on a plan).
- The other forms built with `formRow()` in `workspace.js`.
