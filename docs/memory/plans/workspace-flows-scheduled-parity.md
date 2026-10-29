---
name: workspace-flows-scheduled-parity
description: Parity rows of workspace/workspace-flows-scheduled-merge.md: every scheduled-action key and rule and the slice that keeps it; the test that maps them is in workspace/workspace-flows-parity-test.md
---

## How does the test read keys?
- workspace/workspace-flows-parity-test.md: `workspace_flows_parity_table()`, a reader per group; keys behind a probe come from source (tests/helpers/source_reader.php), the screen's from workspace.js.
- A JS line only made dead (`false &&`) passes.

## Which rows? (merge slice)
- Action (1-3): name, rules, action, follow (on, do), channel_id or note_id; a 5.81 then_action as a done follow-up; the form's request, what each route takes.
- When (3): `at` (`ws_scheduled_repeats()`, every, until, workdays); `trigger`: by hand or chain; `join`: any public channel (0) or one (`ws_scheduled_greets()`), a start per newcomer; `event` (`ws_events_catalog()`, each kept by `pg_event_workspace_events()` or `ws_events_own()`). Starts: drivers, `ws_scheduled_execute()`, `ws_event()` emitters, joins.
- Event filters (3): form page, order status, task status, approval outcome, one channel; the creator must see each workspace event (`ws_events_creator_sees()`), at run time too.
- Conditions (3): record (`ws_scheduled_record_types()`; `_operators()`, within/overdue on dates only), metric (`_metrics()`, `_count_operators()`, channel or threshold, since the last run), workday, weekdays, hours; a failed one skips with its reason.
- Steps (3): post (or the channel joined, -1); email (or a site page); notify; task (priority); task_digest (filters, mine or channel, AI recap only in a background run); webhook (formats); web_check (status: redirect, contains, max_ms; ssl: days left); report (counts, param); trigger (delay); change (`ws_change_types()` minus `ws_scheduled_change_skipped()`, update or delete, every update field); approval (any, all); checkin. Number caps (delay, rules, people, texts).
- Webhook body (3, 5): an outside contract (n8n, Zapier); the id kept when converted.
- Follow-ups (3): `ws_scheduled_outcomes()`, at most 5 in order; one failing fails a good run.
- Chains (3): depth 8: `ws_event_depth()` hands it to caused events, `ws_events_start()` drops and `ws_scheduled_start()` refuses at 8; 60 starts an hour (join, event); self-retry.
- Tokens (3): `_placeholders()`, `ws_scheduled_fill()`, `ws_events_fill_values()`, {{count:key:param}}.
- States (5): `ws_scheduled_states()`; a one-off is done once run (by hand too); a paused repeating one run by hand stays paused; resume counts from now, refuses a passed time; a failed one edited is active; cancel drops waiting starts; a converted anchor keeps these.
- News (5): channel line, Site Log, creator's scheduled_failed, on a first run, a new outcome, a single action or by hand (`ws_scheduled_report()`); `ws_scheduled_runs` deleted only with a scheduled message.
- Record writes (3): `ws_change_source()` kind scheduled names the action; the run logs a line.
- Screen (1): state chips, "in #channel / in note"; card (non-staff: name, time, state), follow-ups, starters, last run, Edit, Pause/Resume, Run now, Duplicate, History, Cancel; in the list, under its message, in the note strip; decision under it; form (18 templates, AI sentence, next runs); routes and `pg_panel_actions()` rows kept.
- Entry points (1, 4): list, composer insert menu (not discussions), notes; propose_scheduled_action keeps its drafts as flow draft cards (C5b leftover); older drafts of kind scheduled: slice 4 (4c2 #2323: a draft card opens a new flow).
- Links in (1): ?view=scheduled, the main menu's Workspace list (`ws_nav_groups()`, answered in place on the channel screen; "Scheduled messages" for others), scheduled_failed without a card, scheduled_message (staff: the link names the Messages tab), tour step.
- API (5): public inbox kinds scheduled_notice (with its text), scheduled_failed.
