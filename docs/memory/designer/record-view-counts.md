---
name: record-view-counts
description: Views on designed record pages (form counters, people only, counted after sending) and form lists ordered by views, comments, recent views or newest activity; why not visitor_content_hourly
---

## What counts a view?
- `_render_system_widget_form_item_view()` registers the record it showed (`pg_sw_record_view_seen()`, first wins); `get_page.php` counts it after `echo $content;` with `pg_sfv_count_view()`: `submitted_form_info.number_of_views` and the day's `submitted_form_view_stats` row, per (record, page), as legacy `get_form_item_view.php` does (#1241; before, designed pages never counted).
- The page prints the count before this view (legacy is the same).

## What does not count?
- Automated clients: `pg_request_is_automated()` (`seo.php`), the rule `init_tracking()` uses for the visitor row (IS_BOT, a non-human UA class, `Pinegrap/` clients).
- HEAD and POST (`pg_sw_should_count_record_view()`).
- Background renders (search index, SEO pass, canvas preview, e-mails, CLI): they never reach the tail; the widget also skips them.
- A record not found, denied or outside the filters; a record page served as the home page (`/?r=`, page id 0, whose printed counter is 0 too).
- Staff views and the visitor-tracking switch do not matter (legacy parity).

## Why not the visitor rollup?
- `visitor_content_hourly` (dashboard Most Visited, API top content, llms.txt, SEO impact) counts crawlers and not-found record addresses, is written only with visitor tracking on, is hourly and never swept. The site's "most read" reads the form counters, so the two differ on purpose. Seeding the counters from it was rejected (bot counts).

## How is a duplicate row avoided?
- `submitted_form_info` has no unique key. The writer updates first; on 0 rows it takes `GET_LOCK('pg_sfi_<form>_<page>', 1)`, updates again, inserts if still 0. A timed-out lock (`0`) drops that one view; no lock service (query fails or NULL, e.g. Galera) inserts unlocked like legacy, since the readers tolerate a duplicate.
- Readers of the sort use `MAX()` subqueries, never a join. The comment writers (`edit_comment.php` and the API through `pg_comment_refresh_record_info()`; `add_comment.php`, `job.php` and `submit_order.php` with their own copies) all read, DELETE, INSERT, unlocked; a UNIQUE key is deferred (schema step with dedup).
- Every statement is `@mysqli_query`: `db()` makes a failed query an error screen.

## How do lists sort?
- `order_by_field` / `order_by_2_field`: `number_of_views`, `number_of_comments`, `recent_views` with `order_recent_days` (today and N-1 days before, 1-180, default 7, `pg_sw_recent_views_days()`), built by `pg_sw_counter_order_sql()`. Without the daily table `recent_views` reads all-time views.
- `newest_activity_date_and_time`: the later of `forms.submitted_timestamp` and the `created_timestamp` of the comment the detail page's `submitted_form_info.newest_comment_id` names (MAX subquery, integers, 0 when none). The source the token prints and the classic list orders by, so date and order agree.
- The names win over a same-named form field (the editor drops that option).
- They read the list's `detail_page_id`; without one the list keeps date order and the panel says so.
- Editor: Statistics group in Sort field, Days input for recent days (clamped on save); a switch to catalog listing maps them to `sort_order`.
- Performance: each candidate record runs one subquery before `LIMIT`; pair a "most read" box with a date filter and `max_results = items_per_page`.

## What else?
- The six-month sweep (1 in 500) runs from both writers (`pg_sfv_sweep_sampled()` on the designed path).
- Backups carry no daily rows: after a restore recent views start again, all-time counters survive.
- Legacy still counts crawlers, index runs, the SEO pass and e-mail renders: an owner question, as is a rollup that skips crawlers.

Related: docs/memory/visitors/article-view-daily-bucket.md, docs/memory/designer/submitted-form-system-fields.md
