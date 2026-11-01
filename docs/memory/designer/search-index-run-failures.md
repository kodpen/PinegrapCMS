---
name: search-index-run-failures
description: How update_search_index.php survives a bad page, event, record or file (isolated render, old row kept) and reports failures and an early stop in the Site Log
---

## What did it fix?
- One page whose render threw an `Error`, failed a query (`output_error()` exits) or one office file the converter could not read ended the run, and every later run at the same place: later pages and every designer record never got in (SA1).
- Before that (#1197 follow-up) a run that left designer records out still said "updated successfully"; only one `error_log` line knew.

## How is each item read?
- Pages, calendar events and classic form records: `pg_search_index_render($page_id, $properties)` (`includes/fn/site_search.php`): `pg_error_throws(true)`, `catch (Throwable)`, `finally` puts back `$_GET` (whole array), the output buffer level and the flag. Designer records keep their own `try` ([[designer-records-site-search]]).
- A page, event or classic record that fails keeps its row: `pg_search_index_keep_item($url, $timestamp)` sets its `timestamp` to the run's, so the sweep spares it with its last text. A failed page's calendar events or records are still read. A designer record that fails has no item (open architect question 5 of `plans/search-and-audits.md`).
- Files: each file's text extraction in its own `try` under the flag; a failure keeps the old row. `shell_exec` only after `function_exists()` (named in `disable_functions` it is "Call to undefined function" on PHP 8). `.doc`/`.ppt` read through `pg_search_index_file_bytes()`: '' for a missing, unreadable or empty file (`fread()` on a failed `fopen()` was a `TypeError`, on 0 bytes a `ValueError`).

## What does a run write?
- Every failure, any kind, goes through `pg_search_index_failed($index_failures, $index_failure_notes, $note)`: counted, the first five named with `pg_search_index_failure_note($name, $code, $message, 200, lang($kind))`: `<kind> <page> (<code>): <message>`, kinds Page, Event, Record, Record list, File; code: reference code or event id; message on one line, cut to 200 characters (UTF-8 safe). Pure.
- After the sweep: one Site Log line "The search index could not read {var:1} item(s): {var:2}", as the run's user from the panel button, no user from the job.
- The finish window: a warning icon and "updated, but N item(s) could not be read. The Site Log names them." instead of the success sentence. `#finish_message` keeps its id: the status region copies its text.

## What if the run stops before its end?
`$search_index_run` holds the stage (pages, records, files, sweep) and the last item started; a shutdown function writes "The search index run stopped before its end: …" with `pg_search_index_stop_note()` (pure): stage, item and `error_get_last()` only when fatal (message, file basename and line). `done` is set at the end of every run, also one that stops at its time budget ([[search-index-resumable-pass]]). A process killed by a signal runs no shutdown function.

## Which tests cover it?
`tests/search_index_isolation_test.php` (render isolation with an `Error` and `output_error()`, notes, file reader, stop note, source checks); `tests/designer_search_index_test.php` (designer branch).

## What if two runs overlap?
- One lock for every start (#4765): the dispatcher, the panel button and a crontab run claim the `update_search_index` row of `cron_runs` through `pg_cron_claim()` (4 h, released by a shutdown function with `pg_cron_release()`). A second button start gets "The search index is already being updated…"; a second crontab start leaves quietly. Before the 2026.4.8 upgrade (no `locked_until`) there is nothing to claim.
- The sweep deletes `timestamp < $timestamp` (`pg_search_index_sweep_sql()`): a run started later keeps its rows when an earlier one ends first. Before (`!=`), the first run to finish deleted every row the other had rewritten and pages left the search until the next run.

Test: `tests/search_index_overlap_test.php` (sweep, lock length).
