---
name: search-index-item-parts
description: Parts inside a search index item (page render, events, classic records, a host's records): budget after each part, pause and resume, the part marker, what a tick that died keeps
---

## What is a part?
- Inside one item of the pass ([[search-index-resumable-pass]]): a page item's own render, then each calendar event (by id), then each classic record (by id); a record-list host's records, list by list in widget order, records by id. A part's key is its address, known before its render.
- Before, the budget was checked only between items: a list whose records took longer than one tick was killed every tick, skipped, and its records swept by every full pass.

## What happens when the time runs out inside an item?
- The budget is checked after each part (`$search_index_part_end` in `update_search_index.php`). Out of time: `pg_search_index_pause()` keeps the item as `current` with `paused`, the checkpoint `part` and the carry; the next tick's `pg_search_index_begin()` resumes it. Nothing skipped, nothing named.
- On resume a part is passed when this pass wrote it (`pg_search_index_part_step()`, `$written`): its address is in the carry (`event_urls`, `record_urls`, `designer_record_urls`), or for the page's own render the item has a checkpoint.

## Why a marker beside the cursor?
- The carry is large on a big site, so the cursor (with its carry) is written only at pauses and every 5 seconds; it always matches the place it is saved with (the files stage reads its linked names). Before each part only `data/temp/search_index_part.json` (`pg_search_index_marker_*()`: started, stage, item, part) is written, removed after the part.

## What does a tick that died keep?
- Inside a part (marker names it, same pass, stage and item): the item is read on from its checkpoint, only that part is skipped (`dead_parts`), keeps its row (`pg_search_index_keep_item()`) and is named "A run ended while reading it". The marker is removed once read.
- Outside any part (a host's list queries, or a marker naming a part already skipped): the item is skipped as before and keeps its rows with `pg_search_index_keep_pages_sql()`: the page's id for a page item (its row, events and classic records carry it), `pg_search_index_host_pages()` (the lists' detail pages) for a host. A file keeps its URL. A row kept one pass too long is acceptable, a live row swept is not.
- A cursor written before parts existed has no `part`, `paused` or `dead_parts`: its item is skipped and named as before (and now keeps its rows).
- Delta runs keep no cursor and write no marker: unchanged. The Site Log stop line names the part being read.

Tests: `tests/search_index_resume_test.php` (`test_search_index_parts_*`).
