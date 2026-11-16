---
name: workspace-plan-drawers-look
description: "Add to the plan" (eventForm) and "New plan" (planDrawer) look the same, built from the kit's form parts (#9887, #9909): the rule, one order and wording, own fields, stores apart
---

## Which two drawers, and what do they share?
- "Add to the plan": `eventForm()` in `assets/js/workspace.js` (Planning Board, Work Calendar, a member's menu, My calendar's `h.planItem`), posts `ws_event_save` / `ws_event_delete`, table `ws_events`.
- "New plan": `planDrawer()` in `assets/js/workspace_my_calendar.js` and its roadmap, posts `ws_personal_plan_save` / `_delete`, table `ws_personal_plans` and its two beside it.
- Both open in `offcanvas()` and build every field from the kit's form parts, `formParts` in `workspace.js` (`h.form`, `PGWsKit.form`); what each part builds: `workspace-drawer-form-parts.md`. PD1 #9887 moved the parts there, PD2 #9909 put "Add to the plan" on them.

## What is the rule for a field in either drawer?
- Build it from the parts, never from `formRow()` or a Bootstrap form class (`form-control`, `form-label`, `form-text`, `input-group`, `row`/`col-*`, `.switch`, `btn btn-*`). People and the customer use the pickers both already use (`peoplePicker`, the customer pickers).
- Only look A's tokens and recipes: `php tools/check_look.php` counts do not rise.

## What is the one order and wording?
- Title; First day and Last day (a pair); All day; Start time and End time (a pair); the drawer's own fields; Note; Customer.
- Labels: "Title", "First day", "Last day", "All day", "Start time", "End time", "Note", "Customer (optional)". The task form keeps "Starts" (`start_date`).
- A line under the header (`ws-plan-private`, an icon and meta text): "Add to the plan" says where the item goes, following Who (`event_where_people`, `_dept`, `_company`, icon `bi-people`); "New plan" says who sees the plan.
- Footer: Delete at the left on a saved item, Save at the right; no Cancel, the header's cross or Esc closes the drawer.
- No sideways scroll at 390 px or wide, light or dark; pairs stay side by side at 390 (`workspace-plan-drawer-phone.md`).

## What does each drawer keep for itself?
- "Add to the plan": Kind above Title; Who after the times as a `pg-seg` (People, Department, Company, each offered only to who may use it) with the people picker or Department under it; after Customer the meeting link row (box, Jitsi, Join, wraps on a phone) and the Meeting note button. Which fields show per kind, the defaults, the limits and the posted names are those of before #9909.
- "New plan": Reminder and Repeat after the times ("Repeat every" with its unit on one line), Who can see, the roadmap last; the read-only state of somebody else's plan.

## Do the two stores join?
- No. A plan is never a `ws_events` row and nothing moves between them (`workspace-personal-calendar-rules.md`); only the look is one.

## What did the owner see?
- Owner (2026-11-16): "Evet, bu görünümle çıksın", on one sheet with both drawers side by side, the line under the header and no Cancel (agent channel messages 6403, 6404, 6411; `workflow/decisions-made-product-61.md`).

## What waits for a later yes of the owner?
- One store, one drawer or one engine for both; a button that turns one into the other; any column, table, action, API route or MCP tool for it.
- A new field in either drawer (Reminder or Repeat on a plan item, Kind on a plan).
- The other forms built with `formRow()` in `workspace.js` moving to the parts.

Tests: `tests/workspace_plan_drawers_look_test.php`, `tests/workspace_my_calendar_test.php` (`test_workspace_my_calendar_drawer_builds_from_the_form_parts`), `tests/workspace_personal_plan_steps_test.php` (`test_workspace_personal_plan_drawer_fits_a_phone`).
