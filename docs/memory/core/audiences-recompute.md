---
name: audiences-recompute
description: How a contact group filled by rules is recomputed: pages of contact ids, the rule-row diff, the hour and 20-second budget of the general job, the announce once a pass, Refresh now (audience_refresh)
module: audiences
---

## What does a recompute do?
- `pg_audience_recompute($group_id, $budget)` (`includes/fn/audiences.php`) walks the contacts in pages of 10,000 ids (`PG_AUDIENCE_PAGE`) from `contact_groups.compute_cursor`. Per page it reads the ids the rule selects (`pg_audience_sql_page()`), the group's `rule` rows and its rows of any other source, and `pg_audience_diff()` (pure) gives `add`, `remove`, `convert`, `drop`.
- Every row of a dynamic group ends up a `rule` row (#6307): a hand, form or product row whose contact the rule selects is turned into a `rule` row (`convert`), or deleted when the contact also has a `rule` row (`drop`); a contact the rule no longer selects leaves with all its rows (`remove`, any source). New members are inserted as `rule`. 500 ids a statement (`pg_audience_write_queries()`, pure).
- Why: a static group turned dynamic keeps its members only until the first recompute (`plans/audiences.md`), and a row the recompute could not reach (a merged contact's, a switched group's) kept a contact in the group for good. The doors refuse new hand rows (`audiences-doors.md`).
- Removed contacts of other sources are announced with the rest; converted ones stay members and are not.
- The last page runs to the top of the id range, so rule rows of deleted contacts go too.
- A pass that ends stamps `computed_at`, puts the cursor back to 0 and announces the whole change once through `pg_announce_contact_group_members()` (`contact_group.members_changed`: Flows, webhooks, campaign-profile triggers react as for a hand edit). A pass that stops at its budget keeps its cursor and the ids changed so far in `data/temp/audience_<id>.json`; the next call goes on from there.
- Returns null for a group that is not dynamic, before the upgrade, or on a failed query.

## When does it run?
- The general job (`job.php`) calls `pg_audience_job_run()`: at most `PG_AUDIENCE_TICK_SECONDS` (20) of the tick, capped by `pg_cron_time_left()`. Due groups (`pg_audience_due_sql()`): kind `dynamic` with a pass under way or `computed_at` an hour old (`PG_AUDIENCE_INTERVAL`), passes under way first, then the oldest run. A group that stops at the budget ends the tick's turn; it goes on next tick.
- A run that fails is stamped as run (cursor 0, state file removed), so one broken group waits its hour instead of heading every tick; what it had written by then is not announced.
- A log line only when something was added or removed.

## What is Refresh now?
- Panel action `audience_refresh` (`includes/panel/audiences.php`, `contact_group_id`): any role with the contacts right and access to that group (`validate_contacts_access()`, `validate_contact_group_access()`, answered in JSON), token checked. One request recomputes for 5 seconds and answers `done`, `added`, `removed`, `members`; the screen asks again until `done`, as the search index window does. 409 when the group is not filled by rules. The screen side is AU3.

## Tests?
- `tests/audiences_recompute_test.php`: the diff on fixed sets, the chunked statements, the page query, the budget stop (`pg_audience_next_step()`), the due order, nothing before the upgrade, the job hook and the action row.

Related: docs/memory/core/audiences-rules.md
