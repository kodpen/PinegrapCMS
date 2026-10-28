---
name: workspace-flows-form-retirement
description: Retiring the scheduled actions form (merge plan R1-R4): the functions, routes, strings, styles and tests that go, what cards and scheduled messages keep
---

## What goes with the form? (line numbers of 2026-10-28)
- workspace.js: `scheduledForm()`, `saSentenceBox()`, `saTemplates()`, `saFollowRow()` (16086-16851, R3a); `saActionEditor()`, `saActionTypes()`, `saConditionRow()`, `saMetricPicker()`, `saArea()`, `saNumber()`, `saDayPicker()` (15178-16085 less the kept ones, R3b). Its three openers were closed in R2 (`workspace-flows-scheduled-ways-in.md`): the list's New is gone, Edit on a card and in `scheduledGraph()` call `scheduledEdit()`; only the form's own template and AI sentence calls remain.
- workspace_events.js `whenBox()`, `templates()`; `scheduledEditor()` of workspace_approvals.js and workspace_status.js.
- Panel routes `ws_scheduled_save`, `ws_scheduled_preview`, `ws_scheduled_targets`, `ws_scheduled_draft` (actions.php case and `pg_panel_actions()` row); `ws_scheduled_save()`; `ws_scheduled_draft_saved()`; the form's keys of `ws_scheduled_js_config()` (`draft`, `max_follow`, `depth`, `min_minutes`, `placeholders`, `formats`, `outcomes`: check each against workspace_flow.js and flow_templates.php first) and its strings in `ws_scheduled_js_strings()`, `ws_scheduled_draft_js_strings()`.
- backend.src.css: the form's `ws-sa-*` classes (about 11578-11652, 13272-13520); keep those the card, list, history and the approval form use.
- Tests: parity readers `request`, `templates`, `entry_points` and the `routes` line of the form; the lines keyed `scheduledForm.*`, `ws_scheduled_save.*`, `ws_scheduled_preview.*` and the `ws_scheduled_js_config` limits; `data.then_action` stays (`ws_scheduled_input()`).

## What stays, and who uses it?
- `saRecordPicker()`, `saValueInput()`, `saLabelled()`: the record drawer's quick edit (`quickEditSection()`). `saLabelled()`, `saText()`, `saChannelSelect()`: `featureHelpers()` for the approval form.
- `scheduledList()`, `scheduledCard()`, `scheduledGraph()` (read-only), `flowOpened()`, `scheduledTick()`; routes `ws_scheduled_list`, `_get`, `_status`, `_run_now`.
- `ws_scheduled_preview()` (the AI draft card's words), `ws_scheduled_targets()` (flows.php catalogue), `ws_scheduled_draft()` (the @ai draft), `ws_scheduled_input()`, `ws_scheduled_save_check()` (app proposals of type scheduled), `ws_scheduled_form_data()` (conversion, Duplicate, cards), `ws_scheduled_js_config()` (canvas, flow templates, `CFG.scheduled` gates Duplicate and Add › Scheduled flow).
- Scheduled messages: `scheduledMessageCard()`, `scheduledMessagesList()`, `sm*()`, composer `scheduleSend()`, `drawScheduledBar()`, `editScheduled()`, and the `ws_scheduled_message*` routes: none of them call a form function.
- `ws_scheduled_convert_due()` stays for rows of a site upgraded late; nothing makes new unconverted rows once `ws_scheduled_save()` is gone.

## Which starters does the flow gallery lack?
- welcome (join) and the five event ones (`order_event`, `form_event`, `stock_event`, `overdue_event`, `approval_event`): in the gallery since R1 (a flow fills `{{newcomer}}`, `{{channel}}`, `{{record}}` since #2205).
- forms, orders ("since the last run") and chain (started by another action): issue #2753 question 2 (R1b).

## How big is each step?
- R1 about 300 lines (flow_templates.php), R2 done, R3a about 900, R3b about 970, R4 about 700 (routes 85, save 95, draft saved 35, config and strings about 150, styles about 330). `.min.js` rebuilt each time, not counted.
