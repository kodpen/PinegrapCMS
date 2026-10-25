---
name: workspace-flows-scheduled-parity
description: Parity rows of plans/workspace-flows-scheduled-merge.md: every scheduled-action key and rule, the slice that keeps it, how its test reads keys without a database
---

## How does the test read keys?
- tools/test.php has no database, ECOMMERCE or ERP_ENABLED: a call misses keys behind a table probe or constant (time_logged_week, shop and ERP metrics, order.*, stock.low, approval.decided, guest.message). Read sources with `pg_test_function_source()`, as tests/workspace_record_kinds_test.php does.
- Card buttons exist only in `scheduledCard()` (workspace.js): scan it or add a PHP list.

## Which rows? (merge slice)
- When (3): `at` (`ws_scheduled_repeats()`, every, until, workdays); `trigger`: no time, by hand or another action ('chain' template); `join`: any public channel (0) or one, unarchived public or private only (`ws_scheduled_greets()`), a start per newcomer; `event` (`ws_events_catalog()`).
- Event filters (3): form page, order status (`ws_events_order_statuses()`), task status (`ws_task_statuses()`), approval outcome (`ws_events_approval_outcomes()`), one channel; the creator must see each workspace event (`ws_events_creator_sees()`), the right checked again at run time.
- Conditions (3): record (`ws_scheduled_record_types()` kinds, fields; `_operators()`, within/overdue on dates only), metric (`_metrics()`, `_count_operators()`, channel or threshold, since the last run), workday, weekdays, hours; a failed one skips with its reason.
- Steps (3): post (or the channel joined, -1); email (text, or a site page as HTML by `get_page_content()`, subject its title if empty); notify; task (channel, people, due in); task_digest (`ws_scheduled_digest_filters()`, mine or channel, default people the assignees or members, missed with an AI recap only in a background run, `WS_RECAP_MAIL_AI_BUDGET`); webhook (`_webhook_formats()`); web_check (status: redirect, contains, max_ms; ssl: days left); report; trigger (delay); change (`ws_change_types()` minus `ws_scheduled_change_skipped()`, update or delete); approval; checkin.
- Webhook body (3, 5): an outside contract (n8n, Zapier): event workspace.scheduled_action, site, action {id, name, run}, text, result, status, source, at; kept with the id when converted.
- Follow-ups (3): `ws_scheduled_outcomes()`, at most 5 (`WS_SCHEDULED_MAX_FOLLOW`) in order; one failing fails a good run.
- Chains (3): depth 8: `ws_event_depth()` hands it to caused events, `ws_events_start()` drops and `ws_scheduled_start()` refuses at 8; 60 starts an hour (join and event too); self-retry. Flow runs start at depth 0 (`ws_flow_run_start()`).
- Tokens (3): `_placeholders()`, `ws_scheduled_fill()`, `ws_events_fill_values()`.
- States (5): `ws_scheduled_states()`; a one-off is done once run, by hand too; a repeating one paused and run by hand stays paused; resume counts from now, refuses a passed time; editing a failed one makes it active; cancel drops waiting starts. Flows: draft, live, paused, binned; a converted anchor keeps its own.
- News (5): channel line, Site Log, creator's scheduled_failed, only on a first run, a new outcome, a single action or by hand (`ws_scheduled_report()`); `ws_scheduled_runs` never swept.
- Record writes (3): `ws_change_source()` kind scheduled names the action on the record ("… by the scheduled action “X”"); the run logs its own line.
- Screen (1): state chips, "in #channel / in note" (`ws_scheduled_list()`); card (non-staff: name, time, state), starters (`ws_scheduled_starters()`), Edit, Pause/Resume, Run now, Duplicate, History (by hand, source), Cancel; note strip; decision under the card; form (18 templates, AI sentence, next runs).
- Entry points (1): list, composer insert menu (no discussions), note toolbar (note edit right), @ai draft Open.
- Links in (1): ?view=scheduled (staff, and "Scheduled messages" for others), scheduled_failed without a card, scheduled_message, tour `[data-ws-nav="scheduled"]` (optional: drops silently).
- API (5): public inbox kinds scheduled_notice (with its text), scheduled_failed.
