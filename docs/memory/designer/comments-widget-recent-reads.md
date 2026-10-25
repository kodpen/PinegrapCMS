---
name: comments-widget-recent-reads
description: The statements a list of recent comments sends (sources, candidates, newest per record, page threads, mine/pending, rows, titles), the merge, when it is exact, measured cost, indexes, page cache
---

## Which statements does a list send?
- Q0 `pg_comments_recent_sources()`: the ≤5 pages in one SELECT; the rest (widgets, folders, edit access, pretty URLs) from the request's caches; a submitter_only view reads its form page's folder.
- Published, record source: `pg_comments_recent_candidates_sql()` over `submitted_form_info AS recent_info` (the gate's own aliases include `submitted_form_info`), joined to `forms` with the gate, `ORDER BY newest_comment_id DESC LIMIT 2k` (k = limit + 1); then `pg_comments_recent_latest_sql()`: one UNION ALL part per kept record (k records), newest k published each.
- Mine and pending, record source: `pg_comments_recent_direct_sql()`, one statement with gate and scope. Page sources: `pg_comments_recent_pages_sql()`, one UNION ALL part per page.
- Every `ORDER BY` is `created_timestamp` alone (a MyISAM key has no id suffix); `pg_comments_recent_merge()` sorts by (time, id), drops repeats, cuts to the limit and sets `more`. No COUNT, no OFFSET, no pages.
- Then the drawn rows by id under the scope again (`pg_comments_recent_rows_sql()`), and titles only for drawn records (`pg_comments_recent_titles_sql()`: RSS title, plus `form_fields.name IN (...)` only when a view names a title field; an empty `IN ()` fails the page).

## When is the published list exact?
- When ids grow with time: the n newest comments belong to the n records whose newest comment (`newest_comment_id`, the newest published by time, `pg_comment_refresh_record_info()`) has the highest id. A scheduled comment published by job.php keeps its id but takes a new time; the 2k candidates are the margin. A record without a counter row appears after its next comment or moderation (11.0.33 recounts designed pages).

## What does it cost?
- Sandbox, MariaDB 10.11.14, PHP 8.3 (2026-10-25): 250k comments, 200k on 10k records of one topic page, 50k on one doc page's own thread; medians of 5, limit 5 / 50:
  - InnoDB: candidates 0.3-1.6 ms, newest per record 0.5-6 ms; page thread published 105-203 ms (the optimizer takes an index_merge of `published`, `item_id`, `page_id` with a filesort, not `idx_thread`, whose range it overestimates 2x), pending 0.3-4 ms; record pending 34-64 ms; mine: record 1.5-109 ms, page thread 174-259 ms (one cold run 306 ms). Whole read, limit 5, topic + doc: published 107-136 ms, mine 109-145, pending 36-45.
  - MyISAM: page published 80 ms, mine page 235-249 ms, record mine 59-81 ms, record pending 55-64 ms.
  - The published list equalled a full scan's newest five on both.
- Record-only lists (a forum) stay near 2 ms published. No index was added; `idx_author (created_user_id, created_timestamp)` would serve mine (owner question under fixed rule 5 if mine grows past 300 ms).

## Which indexes serve it?
- `submitted_form_info.newest_comment_id` (candidates), `comments.idx_thread` (pending page threads; meant for the others), `item_id`, `created_timestamp`, `published`. `idx_parent` is not used.

## Can the page cache keep it?
- Anonymous output reads no session: yes, stale up to PAGE_CACHE_TTL. Signed-in answers are personal and not kept; an address with a query (`?board=`) is never kept (`pg_page_cache_decide()` reason `query`), so a filtered board view pays its statements on every request.

## How was it checked?
- `tests/comments_recent_test.php`: SQL text, closed gates send nothing, merge, titles without a title field. Timing script in the sandbox above.
