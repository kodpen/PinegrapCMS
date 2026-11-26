---
name: audiences-group-loop
description: A contact group filled by rules that names itself is refused, and two recompute passes of one group never run together (#11789): pg_audience_group_loops(), the per-group lock
module: audiences
---

## Which rule is refused?
- One whose "Contact group" conditions reach the group itself, directly or through the rules of other dynamic groups: `pg_audience_group_loops($id, $rules, pg_audience_dynamic_rules())` (pure walk, nested sets included; a hand group ends the walk).
- Edit Contact Group refuses the save on the Fill this group by rules switch; the recompute treats a stored one as a failed run (null, nothing written, waits its hour; the API refresh answers 500, the panel 409).

## Why?
- "Not in this group" added every contact on one pass and removed them all on the next, starting the joined flows and campaign profiles for everyone every two hours (#11789).

## Can two passes of one group run at once?
- No: `pg_audience_recompute()` takes `GET_LOCK` on `pg_audience_lock_name($id, $database)` (database hashed in, under 64 characters) and reads the cursor inside it, then runs `pg_audience_recompute_pass()`.
- A run that finds the lock taken answers `pg_audience_busy_answer()`: not done, nothing changed, so the job ends its turn and Refresh now asks again.
- Before (#11789), the job tick and Refresh now or the API refresh read the same page and both inserted its rule rows (the xref has no unique key): two rows and two joined announces per contact.

## Tests?
- `tests/audiences_rules_test.php` (`test_audiences_group_loops`), `tests/audiences_recompute_test.php` (lock name, lock before the pass, busy answer).
