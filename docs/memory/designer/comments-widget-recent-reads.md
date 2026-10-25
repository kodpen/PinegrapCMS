---
name: comments-widget-recent-reads
description: The statements a list of recent comments sends, the merge, when it is exact, index hints, measured cost, the open idx_author question, page cache
---

## Which statements does a list send?
- Q0 `pg_comments_recent_sources()`: the ≤5 pages in one SELECT; the rest (widgets, folders, edit access, pretty URLs) from the request's caches; a submitter_only view reads its form page's folder, a custom form its settings row (signed out only).
- Published, record source: `pg_comments_recent_candidates_sql()` over `submitted_form_info AS recent_info` (the gate's own aliases include `submitted_form_info`), joined to `forms` with the gate, `ORDER BY newest_comment_id DESC LIMIT 2k` (k = limit + 1); then `pg_comments_recent_latest_sql()`: one UNION ALL part per candidate record (up to 2k), newest k published each.
- Mine and pending, record source: `pg_comments_recent_direct_sql()`, one statement with gate and scope. Page sources: `pg_comments_recent_pages_sql()`, one UNION ALL part per page, `FROM comments IGNORE INDEX (item_id, published)`: without it MyISAM intersects item_id and InnoDB published with page_id and sorts the page's whole thread (~0.1 s) instead of reading `idx_thread` backward. Both keys are in every install (tested on the dumps); `FORCE INDEX (idx_thread)` would fail before 11.0.31.
- Every `ORDER BY` is `created_timestamp` alone (a MyISAM key has no id suffix); `pg_comments_recent_merge()` sorts by (time, id), drops repeats, cuts to the limit, sets `more`. No COUNT, OFFSET or pages.
- Then the drawn rows by id under the scope again (`pg_comments_recent_rows_sql()`), and titles only for drawn records (`pg_comments_recent_titles_sql()`: RSS title, plus `form_fields.name IN (...)` only when a view names a title field; an empty `IN ()` fails the page).

## When is the published list exact?
- When ids grow with time. job.php publishes a scheduled comment with the current time and writes its older id as `newest_comment_id`, so its record ranks low: it is listed while among the 2k candidates (all are read on), otherwise after the record's next comment. Ranking by time (a join on `newest_comment_id`) was left out: 27-50 ms on 10k records against 0.2 ms. A record without a counter row appears after its next comment or moderation (11.0.33 recounts designed pages).

## What does it cost?
- Sandbox, MariaDB 10.11.14, PHP 8.3 (2026-10-25): 250k comments, 200k on 10k records of a topic page, 50k on a doc page's own thread; medians of 5, MyISAM and an InnoDB copy. Whole read, limit 5, topic + doc: published 1.9-2.2 ms; pending 36-56 ms; mine with comments 54-56 ms; mine of a member made 5k comments ago with none 14-60 ms; mine with none and no creation time 309-351 ms.
- Statements, limit 5 / 50: candidates 0.3-6.6 ms, newest of every candidate 0.8-8.2 ms (2,004 rows at 50); page published 0.1-0.4 ms; page pending 0.3-2.6 ms; record pending 36-58 ms; record mine with none, unbounded 230-286 ms (the created_timestamp key walked over the table), bounded 5-29 ms; page mine with none 55-69 ms.
- Published equalled a full scan's newest 5 and 50 on both.

## Which indexes serve it, and which is missing?
- `submitted_form_info.newest_comment_id` (candidates), `comments.idx_thread` (page threads, newest per record), `created_timestamp` (record mine/pending). `idx_parent` is not used.
- Nothing finds one author's comments: 'mine' for a member with none in the sources and no `user_created` scans; the whole read passes 300 ms. `idx_author (created_user_id, created_timestamp)` as a 2026.11.0 sub-step is an owner question (fixed rule 5), not asked yet.

## Can the page cache keep it?
- Anonymous output: yes, stale up to PAGE_CACHE_TTL. Signed-in answers and an address with a query (`?board=`, `pg_page_cache_decide()` reason `query`) are never kept.

## How was it checked?
- `tests/comments_recent_test.php`: SQL text, closed gates send nothing, every candidate read, merge, titles without a title field; the timing script above.
