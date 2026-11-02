---
name: search-index-resumable-pass
description: update_search_index.php as a resumable pass (stages, cursor file, skip rule, run budgets, panel window requests, sweep against the pass start) and the hourly delta run between full passes
---

## How does an index pass run now?
- One pass in stages, `pg_search_index_stages()`: `pages` (with each page's calendar events and form records, one item), `designer` (the visual editor's record lists, one item per host page), `files`, `sweep` (#4789). Before, one process had to finish everything; a runner's time limit or a proxy cut it and the next run started at the first page again, so the newest pages and the designer records never got in.
- The cursor `data/temp/search_index.json` (`pg_search_index_state_*()` in `includes/fn/site_search.php`) holds the pass's start time, stage, the last finished item (`after`: page id, host position, file id, in ascending order), the item started (`current`), skip notes and `carry`: what a later stage needs from an earlier one (linked names for the files stage, record list hosts, event and record addresses already written, the failure count and notes). Written before each item is read and when a stage ends; removed when the pass ends. Lost (the clean up tool empties `data/temp`): the pass starts again.
- Every row a pass writes carries its start time (`$timestamp = started`), so the sweep after the last stage removes only rows older than the pass, however many runs it took.

## What happens to the item a run ended inside?
- `pg_search_index_begin()` (pure): when the item is the one the cursor names as `current`, it is skipped, counted as passed and named in the pass's failure line ("A run ended while reading it"); the pass never stops at the same item twice.

## How long does one run work?
- Dispatched: `pg_cron_time_left(SEO_ANALYZE_TIME_BUDGET)`; unfinished, `pg_cron_due_again('update_search_index', 60)` and no `pg_cron_ran()` (no `_done` marker until the sweep). Panel window: `SEO_ANALYZE_BUTTON_BUDGET` (20 s) per request; the page says the stage and loads `update_search_index.php?continue=1` until the pass is done. Command line (crontab): no budget, the whole pass in one run.
- Every run takes the job's lock; a dispatched tick and the panel window carry on the same pass. A dispatched tick and a panel request hold the catalogue's 600 s, a command-line run 14400 s (`pg_search_index_claim_seconds()`, `pg_cron_claim($name, $seconds)`; SA14). Before, every start held 14400 s, and a tick a host's job runner killed outright (no shutdown function, no release) kept the heavy lane closed for four hours.

## What runs between full passes?
- The job is hourly (`interval` 3600). A dispatched run with no pass under way and a full pass ended less than a day ago (`pg_search_index_full_due()` on `update_search_index_done`) is a delta (#4837): `pg_search_index_delta_targets()` takes the pages of the index's scope whose own row is older than `page_timestamp`, and the records whose `?r=` row is older than `forms.last_modified_timestamp` (`pg_search_index_record_code()`). It re-indexes those (a record through every visual-editor list that shows it, collected without drawing the pages), keeps no cursor, reads no files, sweeps nothing and writes no completion marker.
- Waits for the full pass: a page or record without a row, a record indexed at its address name, a classic form record, a calendar event, a file.
- `pg_search_index_refresh_soon()` (a new design) moves the `_done` marker back a day as well, so the next tick is a full pass: the new pages have no rows for a delta to find.

Tests: `tests/search_index_resume_test.php` (stages, cursor, skip rule, wiring), `tests/search_index_delta_test.php`.
