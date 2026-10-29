---
name: workspace-flows-form-retirement
description: How the scheduled actions form was retired (R1-R4, done in #2970): what went in each slice, and what cards, scheduled messages, the draft card and the canvas still use
---

## What went, and in which PR?
- R1 (#2769), R1b (#2787): every starter of the form is in the flow gallery (`workspace-flows-template-format.md`); R2 (#2803): Edit of an unconverted row retries `ws_scheduled_convert()`.
- R3a (#2823): `scheduledForm()`, `saSentenceBox()`, `saTemplates()`, `saFollowRow()` (workspace.js), `whenBox()`, `templates()` (workspace_events.js; left `PGWsEvents.icon`, `watchField()`).
- R3b (#2905): `saActionEditor()` and its `sa*` helpers, the two `scheduledEditor()` of workspace_approvals.js and workspace_status.js.
- R4 (#2970): panel routes `ws_scheduled_save`, `_preview`, `_targets`, `_draft` (actions.php cases and `pg_panel_actions()` rows), `ws_scheduled_save()`, `ws_scheduled_draft_saved()`; the form's keys of `ws_scheduled_js_config()` (`chains`, `approvals`, `draft`, `digest_only`, `outcomes`, `formats`, `placeholders`, `depth`, `max_follow`, `min_minutes`, `now`); the form's strings of `ws_scheduled_js_strings()`, `ws_events_js_strings()` (left: `watch_*`) and `ws_scheduled_draft_js_strings()` (left: the draft card's); the `.ws-sa-*` form styles and the `.pg-step` rail in backend.src.css.
- Language shards keep the removed texts: nothing removes a key from them, and an unused key costs nothing.

## What stays, and who uses it?
- `saRecordPicker()`, `saValueInput()`, `saLabelled()`: the record drawer's quick edit (`quickEditSection()`).
- `scheduledList()`, `scheduledCard()`, `scheduledGraph()` (read-only), `flowOpened()`, `scheduledTick()`; routes `ws_scheduled_list`, `_get`, `_status`, `_run_now`. Card classes built from a status (`ws-sa-<status>`, `ws-sa-state-*`, `ws-sa-on-*`, `ws-sa-last-*`) stay in the CSS.
- `ws_scheduled_preview()` (the @ai draft card's words), `ws_scheduled_targets()` (the flow catalogue in flows.php), `ws_scheduled_draft()` (the @ai draft), `ws_scheduled_input()`, `ws_scheduled_save_check()` (the @ai draft's check), `ws_scheduled_form_data()` (conversion, Duplicate, cards).
- `ws_scheduled_js_config()`: the canvas (`changes`, `records`, `operators`, `counts`, `repeats`, `events` in `ws_flow_js_config()`), the flow templates (`site`, `email`, `metrics`, `recap_ai`, `join`, `events`), and `CFG.scheduled` as the gate of Duplicate and Add › Scheduled flow.
- Scheduled messages: `scheduledMessageCard()`, `scheduledMessagesList()`, `sm*()`, composer `scheduleSend()`, `drawScheduledBar()`, `editScheduled()` and the `ws_scheduled_message*` routes.
- `ws_scheduled_convert_due()` stays for rows of a site upgraded late; nothing makes new unconverted rows.

## What did the tests lose?
- tests/workspace_flows_parity_test.php: the routes line of the four routes, the `request` lines of save/preview/draft, the `ws_scheduled_js_config` limits line and the `ws_scheduled_draft_saved` draft line; `data.then_action` stays (`ws_scheduled_input()`).
- tests/workspace_flow_templates_test.php: the titles are no longer compared with `sa_tpl_*`, which went; the chain's text is checked against its `lang()` text.
