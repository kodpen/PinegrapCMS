---
name: search-and-audits-gaps
description: Plan (owner 2026-11-03): what search-and-audits left open; the three jobs on for a new install, an index pass that resumes inside a page or record list; steps SG1-SG3
---

## What is wrong now?
- Decision 2 of the search-and-audits plan has no code (`workflow/decisions-made-product-18.md`: the index, structure and accessibility jobs on for a new install). The starter dumps predate `config.job_dispatch_enabled` and `job_dispatch`, the upgrade adds them off and empty, and `install/index.php` switches on the starter's other defaults (legal documents, large image previews, the 2560 px cap, WebP) but no job. A new site runs none of the three until the operator finds the switch.
- An item a run died inside (a runner that kills `job.php`, a memory or time limit) is skipped and named the next time (`pg_search_index_begin()`), but its rows keep the last pass's timestamp and the sweep (`timestamp < started`) deletes them: the page with its calendar events and classic records, every record of a record list, or the file leaves the search until a later pass reads it. A render that throws keeps its rows (`pg_search_index_keep_item()`); a run that dies does not.
- Items are coarse and the budget is checked only between them. A page item renders the page and every calendar event or classic record it lists; a record-list host (designer stage) renders every record of every list on the page. A list whose records take longer than one tick never finishes where a runner stops `job.php` after a fixed time: killed every tick, skipped, its records swept by every full pass. Big blogs stay out of the search.

## What is proposed?
- SG1: a starter-site install switches the general job's dispatch on with the three jobs; a restored backup and a running site keep theirs.
- SG2: parts inside an item. The budget is checked after each part (an event, a classic record, a designer record), a tick out of time stops between parts and the next one resumes the item there. A tick that dies inside a part skips that part only, keeps its row and names it; one that dies outside any part skips the item and keeps its rows by `search_items.page_id`.
- SG3: close-out.

## Which decisions bind it?
- Decision 2 (owner, `decisions-made-product-18.md`) for SG1. For SG2 the rule already recorded for a failed item: it keeps its last indexed text and is named (`designer/search-index-run-failures.md`). No schema, no new setting, no open question.

## What is outside it?
- A host whose job runner stops `job.php` after a fixed time needs `JOB_TIME_LIMIT` below that limit in `data/config.php`. Without it the tick budget (`SEO_ANALYZE_TIME_BUDGET`, 120 s) does not run out before the kill, so every tick ends inside an item. That is the host's setting.
- The structure and accessibility passes have no skip rule for a page that ends a run (alone longer than the runner's limit, or a fatal error PHP cannot catch): rare, left out.

## Where does it start?
- SG1 and SG2 side by side (the installer against the index), SG3 after both. Steps: `search-and-audits-gaps-steps.md`.
- Read `designer/search-index-resumable-pass.md`, `designer/search-index-run-failures.md`, `designer/designer-records-site-search.md`, `platform/search-and-audits-screen.md`, `platform/starter-site-defaults.md`.
