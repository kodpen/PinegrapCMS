---
name: migration-long-steps
description: How an upgrade step that may outrun the web server's time limit is written: restart-safe work, the 30 s pass budget and install_pause(), backfills with a cursor in config, schema apart from data.
---

## Why can a long step not just raise the time limit?
- IIS FastCGI ends a request after 90 s by default and most proxies in front of PHP after 60 s, whatever `ini_set('max_execution_time')` or `set_time_limit()` says.
- The version marker is written only when `upgrade_to_<version>()` returns, so a request cut off mid-step runs the whole version again next time. Everything before the cut must be safe to repeat (the idempotent helpers, re-runnable data statements), and a change in two phases keys its second guard on the NEW state; otherwise a cut between the phases leaves a state no later run looks at again (`upgrade_2026_4_4_dashboard_appearance()`).

## What does the runner offer?
- `install_pass_seconds()` against `install_pause_budget()` (30 s): a step that walks many tables stops starting new work once the budget is spent. A single ALTER cannot be cut in two, so a step that rewrites a big table is also listed in `install_heavy_tables()` for the preflight warning.
- `install_pause($note, $wait)` ends this pass with a note and the version is not written: the loop runs it again (in the same request up to 720 passes; when the screen drives one version per request, in the next request). Pattern: the InnoDB conversion of 2026.4.8 (`upgrade_2026_4_8_innodb_group()`).
- Pause only after this pass has done work (counted from `$install_runner['executed']` at the start of the step), so every pass moves on and the loop cannot pause for ever. Several ALTERs on one table that may still be MyISAM: `upgrade_2026_11_0_comment_replies()` (`data/comments-table.md`).

## How is big data work split from the schema?
- The step does the schema and a bounded slice of the data; the rest runs outside the upgrade request, a few seconds at a time, with its progress in `config` (`*_max_id` as the ceiling, `*_cursor`, `*_done`), saved after every slice. Examples: the visitor rollups (table and bookkeeping in 2026.3.4, `pg_visitor_backfill_step()` for 20 s in 2026.3.5 and 2026.3.8, the general job carrying on), the form view rollup (2026.4.1, `pg_sfv_backfill_step()`), the password wrap columns (2026.4.4).
- Those three parts (schema, backfill, engine change) were once three versions (2026.3.4, 2026.3.5, 2026.3.6). With one open version at a time they are separate sub-steps or work handed to the general job; a big engine change is left to the operator's Database Engine screen (`data/table-engines-and-row-size.md`).
- A backfill that reads a table the same request has just created asks with `$recheck` (`schema-probe-rules.md`).

Related: docs/memory/release/upgrade-runner-behavior.md, docs/memory/release/how-to-add-migration.md
