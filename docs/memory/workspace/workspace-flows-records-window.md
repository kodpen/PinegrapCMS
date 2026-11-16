---
name: workspace-flows-records-window
description: The flow box "Read the records of a list" (post-digest DG1): where a run's window is read (ws_scheduled_runs.started_at), the first run, the date of a record, what is left out
---

## Where is a run's start moment read?
- `ws_scheduled_execute()` claims the anchor row (`running_at`) and writes the anchor's run (`ws_scheduled_record_run()`, `started_at` = the claim's `time()`) before the flow starts; the flow's context carries that row's id as `sched_run` (`ws_flow_anchor_context()`). That `started_at` is the run's start moment.
- `ws_step_action_row()` gives `last_run_at` 0 for a hidden anchor (only a converted action's run fills it) and is left as it is.

## Which date does a record have?
- The feed (`pg_sw_form_feed_parts()`) dates and orders a record by `forms.submitted_timestamp`, the moment it was made. `forms` keeps no moment of being set live: "Live" and `publish_date` are template fields (#3746).
