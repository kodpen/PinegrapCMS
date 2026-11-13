---
name: audit-log-paging
description: The audit trail list (view_audit_log.php) on the list table driver, sorted and searched on the server; changes read when a line's fold opens; filters stay in the address
module: audit_list
---

## Why was the audit trail heavy, and what changed?
- It printed 50 lines a page with Newer/Older links, each line carrying its whole changes table (indented JSON values): 1.17 MB of HTML on the dev site (issue #2054). Since TD6 (#6386) the list is on the list table driver: `pg_audit_list_source()` (gate role <= 2 and `pg_audit_ready()`) prints the first 100 lines, the next come from `list_table_rows`; `audit_log_list_rows` is gone.
- A row carries only "N fields"; the table is read from `audit_log_changes` (one line's id) the first time its `<details data-pg-audit-line>` opens (inline script in the screen, `toggle` caught on capture; its texts sit on the `[data-pg-audit-failed]` wrapper).
- Rows, sort, search and the changes table live in `includes/fn/audit_list.php` (`tests/audit_list_test.php`).
- From TD6 to PR #9040 the screen answered HTTP 500 on every request (#8993): the Source filter loop overwrote the list source. The trap: `ui/list-table-driver.md`.

## Where do the filters live?
- In the address, as before (links on a line narrow the list from the filters it was drawn with). The source's `params_out` is `{"filters": {...}}`, nested because a filter is called `action` like the panel action; the action checks them again with `pg_audit_view_filters()`. The old `page` parameter is ignored.
- The User filter (and a line's "who" link, which passes the username, or `#id` as the id when there is none): words match the username with LIKE; digits match `user_id` or the whole username, since a username may be all digits (before #5145 only the id, so such an account's link showed someone else's lines).
- Every query binds its values: `pg_audit_view_condition()` gives the filters as `?` placeholders; `pg_audit_view_where()` (the API) writes the same values in with `pg_db_bind()`. A `?` inside a value never breaks a `db_q()` template.

## How is it sorted and searched?
- Heading clicks and the sort menu (`pg_list_state('view_audit_log')`, column keys = sort codes, `pg_audit_list_columns()`): date (default, newest first), who (`username`), action, record (`label`), IP; ties by `id` in the first level's direction. The sort menu sits above the table, outside the filter form (it has its own GET form).
- The driver's search box (`data-pg-list-search="view_audit_log"`, `pg_audit_list_search_columns()` and `pg_audit_list_search_labels()`): every word in the user name, record name, IP, request id, the stored action/type words, the shown source/action/type names (`pg_audit_view_labels()`), a settings line's shown category name (`pg_audit_view_settings_names()`, on settings lines only; before, "Genel" found nothing and only the key `general` did: #4414, PR #4495), or the record's number (`#12`). The "Record name" filter and the CSV export are unchanged; the export still goes newest first by id.

## How are dates, choices, links and widths drawn?
- Start and End go through `pg_date_input_iso()` (the site's order with `/`, `-`, `,` or `.`, so "01.10.2026" is read). A date it cannot read stays in its box with `is-invalid` and "Enter a valid date.", filters nothing, and "Clear filters" shows; before, it vanished silently and every line was listed (#5837).
- Type and Action choices come from `pg_audit_view_filter_options()`: ordered by the shown name (`pg_geo_label_compare()`), the chosen word kept, "All" first (#5842).
- A return request line opens `view_return.php?id=` when the `order_returns` row still exists (#5842).
- The record cell's content sits in `.pg-audit-record` (wraps anywhere, at most 24rem, style in the screen): a long name pinned the column to its one-line width and pushed the IP column off screen (#5836).
