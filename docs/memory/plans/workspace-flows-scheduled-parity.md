---
name: workspace-flows-scheduled-parity
description: Parity rows of plans/workspace-flows-scheduled-merge.md: every scheduled-action key and rule, the slice that keeps it, how its test reads keys without a database
---

## How does the test read keys?
- tests/workspace_flows_parity_test.php: `workspace_flows_parity_table()`, a reader per group. Keys behind a probe or constant (no database or constants in tools/test.php) come from source (`pg_test_function_source()`, `pg_test_ws_change_types()`); card, list, form openers from workspace.js.
- A source reader throws on a shape it does not model (an entry not a literal, a new condition, an option read another way); a JS line only made dead (`false &&`) still passes.
- Workdays cases of `ws_scheduled_next_run()` run in their own PHP process with a stub `ws_day_off()`.

## Which rows? (merge slice)
- Action (1-3): name, rules, action, follow (on, do), channel_id or note_id; a 5.81 row's then_action, read as a done follow-up.
- When (3): `at` (`ws_scheduled_repeats()`, every, until, workdays); `trigger`: by hand or another action ('chain' template); `join`: any public channel (0) or one, unarchived public or private (`ws_scheduled_greets()`), a start per newcomer; `event` (`ws_events_catalog()`).
- Event filters (3): form page, order status, task status, approval outcome, one channel; the creator must see each workspace event (`ws_events_creator_sees()`), checked again at run time.
- Conditions (3): record (`ws_scheduled_record_types()`; `_operators()`, within/overdue on dates only), metric (`_metrics()`, `_count_operators()`, channel or threshold, since the last run), workday, weekdays, hours; a failed one skips with its reason.
- Steps (3): post (or the channel joined, -1); email (or a site page as HTML, its title the empty subject); notify; task; task_digest (`ws_scheduled_digest_filters()`, mine or channel, missed with an AI recap only in a background run); webhook (`_webhook_formats()`); web_check (status: redirect, contains, max_ms; ssl: days left); report; trigger (delay); change (`ws_change_types()` minus `ws_scheduled_change_skipped()`, update or delete); approval; checkin.
- Webhook body (3, 5): an outside contract (n8n, Zapier), event workspace.scheduled_action; the id kept when converted.
- Follow-ups (3): `ws_scheduled_outcomes()`, at most 5 in order; one failing fails a good run.
- Chains (3): depth 8: `ws_event_depth()` hands it to caused events, `ws_events_start()` drops and `ws_scheduled_start()` refuses at 8; 60 starts an hour (join, event too); self-retry.
- Tokens (3): `_placeholders()`, `ws_scheduled_fill()`, `ws_events_fill_values()`.
- States (5): `ws_scheduled_states()`; a one-off is done once run, by hand too; a paused repeating one run by hand stays paused; resume counts from now, refuses a passed time; editing a failed one makes it active; cancel drops waiting starts; a converted anchor keeps these.
- News (5): channel line, Site Log, creator's scheduled_failed, on a first run, a new outcome, a single action or by hand (`ws_scheduled_report()`); `ws_scheduled_runs` deleted only with a scheduled message.
- Record writes (3): `ws_change_source()` kind scheduled names the action on the record; the run logs its own line.
- Screen (1): state chips, "in #channel / in note"; card (non-staff: name, time, state), starters, last run, Edit, Pause/Resume, Run now, Duplicate, History, Cancel; drawn in the list, under its message, in the note strip; decision under it; form (18 templates, AI sentence, next runs); actions.php routes kept (messages' stay).
- Entry points (1, 4): list, composer insert menu (no discussions), note toolbar, @ai draft Open; propose_scheduled_action and drafts of kind scheduled (API list, apply refused): slice 4.
- Links in (1): ?view=scheduled, the rail (`ws_nav_groups()`) and workspace.php sidebar entries (staff's, "Scheduled messages" for others), scheduled_failed without a card, scheduled_message, tour on the sidebar entry (optional).
- API (5): public inbox kinds scheduled_notice (with its text), scheduled_failed.
