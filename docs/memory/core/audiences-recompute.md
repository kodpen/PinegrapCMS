---
name: audiences-recompute
description: How a contact group filled by rules is recomputed: pages of contact ids, the rule-row diff, the hour and 20-second budget of the general job, the announce once a pass, Refresh now (audience_refresh)
module: audiences
---

## What does a recompute do?
- `pg_audience_recompute($group_id, $budget)` (`includes/fn/audiences.php`) walks the contacts in pages of 10,000 ids (`PG_AUDIENCE_PAGE`) from `contact_groups.compute_cursor`. Per page it reads the ids the rule selects (`pg_audience_sql_page()`), the group's `rule` rows and its rows of any other source, and `pg_audience_diff()` (pure) gives what to add and remove.
- Only `source = 'rule'` rows are inserted or deleted, 500 a statement (`pg_audience_write_queries()`, pure). A contact already in the group by hand, form or product is never added a second time and its row is never touched (decision #5033, confirmed in #6319: a recompute never removes or converts a hand, form or product row; a broken or empty rule must not silently empty a group).
- `kind = 'dynamic'` is read again before each page's write (`pg_audience_still_dynamic()`, #6307): a group switched back to static mid-pass ends the pass without writing or announcing, its cursor back to 0 and its state file removed.
- The last page runs to the top of the id range, so rule rows of deleted contacts go too.
- A pass that ends stamps `computed_at`, puts the cursor back to 0 and announces the whole change once through `pg_announce_contact_group_members()` (`contact_group.members_changed`: Flows, webhooks, campaign-profile triggers react as for a hand edit). A pass that stops at its budget keeps its cursor and the ids changed so far in `data/temp/audience_<id>.json`; the next call goes on from there.
- Returns null for a group that is not (or stops being) dynamic, before the upgrade, or on a failed query.

## When does it run?
- The general job (`job.php`) calls `pg_audience_job_run()`: at most `PG_AUDIENCE_TICK_SECONDS` (20) of the tick, capped by `pg_cron_time_left()`. Due groups (`pg_audience_due_sql()`): kind `dynamic` with a pass under way or `computed_at` an hour old (`PG_AUDIENCE_INTERVAL`), passes under way first, then the oldest run. A group that stops at the budget ends the tick's turn; it goes on next tick.
- A run that fails is stamped as run (cursor 0, state file removed), so one broken group waits its hour instead of heading every tick; what it had written by then is not announced.
- A log line only when something was added or removed.

## What is Refresh now?
- Panel action `audience_refresh` (`includes/panel/audiences.php`, `contact_group_id`): any role with the contacts right and access to that group (`validate_contacts_access()`, `validate_contact_group_access()`, answered in JSON), token checked. One request recomputes for 5 seconds and answers `done`, `added`, `removed`, `members`; the screen asks again until `done`, as the search index window does. 409 when the group is not filled by rules. The screen side is `ui/contact-group-rules.md`.

## Tests?
- `tests/audiences_recompute_test.php`: the diff on fixed sets, the chunked statements, the page query, the budget stop (`pg_audience_next_step()`), the due order, nothing before the upgrade, the job hook and the action row. `tests/audiences_unreached_rows_test.php`: other rows left alone, the per-page kind check, `pg_audience_switch_review()`.

Related: docs/memory/core/audiences-rules.md
