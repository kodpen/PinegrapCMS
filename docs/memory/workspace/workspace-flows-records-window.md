---
name: workspace-flows-records-window
description: The flow box Read the records of a list (list_records, #9978): the feed's pick, a page open to everyone, the window of only new records (ws_scheduled_runs.started_at), a record's date, left out, Try
---

## What is it?
- Step kind `list_records` ("Read the records of a list") in `ws_step_kinds()`, its own run (`ws_step_list_records_do()`), outcomes done and failed, tier read, not a role-3 kind. Code `includes/workspace/flow_records.php`; tests `tests/workspace_flow_records_test.php`.
- Settings: `page_id`, `only_new` (0/1). Gives `count`, `left_out` and `records`, a JSON list (title, summary, picture, link, date) a For each box reads, 50 at most.

## How are the records picked?
- By the feed's own query: `pg_sw_form_feed_pick()` in `includes/fn/widgets.php`, split out of `pg_sw_form_feed_parts()`, which calls it with no extras; `pg_sw_form_feed_pick_sql()` builds it (pure). The box widens it through `$extra` (a function given the list's filters).
- Only a page in a folder open to everyone, not in the Recycle Bin, with a public list feed (`ws_flow_records_page_refusal()`); checked at publish and at each run.

## Where is a run's window read?
- From the anchor run the run's context names (`sched_run`): its `ws_scheduled_runs.started_at`, written when the anchor is claimed and before the flow starts. `ws_step_action_row()` is not used and still gives 0 for a hidden anchor.
- From the start of the newest earlier anchor run whose flow run is done or open (`ws_flow_records_edge()`; flow run found by start key `sched:<id>` or the `sched_run` of its context, as Run now keys by token), in; to this run's start, out. A run that failed, was cancelled or started no flow leaves its records to the next.
- Architect (2026-11-16): no earlier counted run: the window opens at the run's version's `published_at`; after a long pause it opens at the last counted run (50 records at most). `only_new` needs a flow that starts at a time (`ws_flow_records_time_problems()`).

## Which date has a record?
- `forms.submitted_timestamp`, the feed's. Where the list's filters hold exactly one "date field is (at most) the current date (and time)" condition on a date field of the form (`ws_flow_records_live_field()`), the later of that and the field's value. `forms` keeps no set-live moment and gets no column (architect, 2026-11-16).
- Left out (D11): records the list shows, dated before the window, changed (`last_modified_timestamp`) inside it. The box's text says so (`ws_flow_records_left_out_note()`).

## What does Try show?
- `ws_step_list_records_try()`: the window a run now would read (last counted run to now), the records and up to 20 left-out titles; nothing moves.

## What is not verified?
- No run on a database; the pure parts and the wiring are under tests. The canvas form, the icon and the palette are DG3.
