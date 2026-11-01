---
name: search-index-resumable-pass
description: update_search_index.php as a resumable pass: stages, the cursor in data/temp/search_index.json, the skip rule, run budgets, the panel window's short requests, the sweep against the pass start
---

## How does an index pass run now?
- One pass in stages, `pg_search_index_stages()`: `pages` (with each page's calendar events and form records, one item), `designer` (the visual editor's record lists, one item per host page), `files`, `sweep` (#4789). Before, one process had to finish everything; a runner's time limit or a proxy cut it and the next run started at the first page again, so the newest pages and the designer records never got in.
- The cursor `data/temp/search_index.json` (`pg_search_index_state_*()` in `includes/fn/site_search.php`) holds the pass's start time, stage, the last finished item (`after`: page id, host position, file id, in ascending order), the item started (`current`), skip notes and `carry`: what a later stage needs from an earlier one (linked names for the files stage, record list hosts, event and record addresses already written, the failure count and notes). Written before each item is read and when a stage ends; removed when the pass ends. Lost (the clean up tool empties `data/temp`): the pass starts again.
- Every row a pass writes carries its start time (`$timestamp = started`), so the sweep after the last stage removes only rows older than the pass, however many runs it took.

## What happens to the item a run ended inside?
- `pg_search_index_begin()` (pure): when the item is the one the cursor names as `current`, it is skipped, counted as passed and named in the pass's failure line ("A run ended while reading it"); the pass never stops at the same item twice.

## How long does one run work?
- Dispatched: `pg_cron_time_left(SEO_ANALYZE_TIME_BUDGET)`; unfinished, `pg_cron_due_again('update_search_index', 60)` and no `pg_cron_ran()` (no `_done` marker until the sweep). Panel window: `SEO_ANALYZE_BUTTON_BUDGET` (20 s) per request; the page says the stage and loads `update_search_index.php?continue=1` until the pass is done. Command line (crontab): no budget, the whole pass in one run.
- Every run takes the job's lock (14400 s, kept for the command-line run); a dispatched tick and the panel window carry on the same pass.

Tests: `tests/search_index_resume_test.php` (stages, cursor, skip rule, wiring).
