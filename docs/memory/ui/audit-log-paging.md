---
name: audit-log-paging
description: The audit trail list (view_audit_log.php) paged, sorted and searched on the server; changes read when a line's fold opens; filters stay in the address
module: audit_list
---

## Why was the audit trail heavy, and what changed?
- It printed 50 lines a page with Newer/Older links, each line carrying its whole changes table (indented JSON values): 1.17 MB of HTML on the dev site (issue #2054). Now the screen draws the filters and the headings; the lines come from `audit_log_list_rows` (`includes/panel/lists.php`, gate role <= 2 and `pg_audit_ready()`, token checked) one page at a time through `pgListServerOptions()`, with DataTables' page length menu and count.
- A row carries only "N fields"; the table is read from `audit_log_changes` (one line's id) the first time its `<details data-pg-audit-line>` opens (inline script in the screen, `toggle` caught on capture).
- Rows, sort, search and the changes table live in `includes/fn/audit_list.php` (`tests/audit_list_test.php`).

## Where do the filters live?
- In the address, as before (links on a line narrow the list from the filters it was drawn with). The screen sends them in `data-pg-list-params` as `{"filters": {...}}`, nested because a filter is called `action` like the panel action; the action checks them again with `pg_audit_view_filters()`. The old `page` parameter is ignored.
- Every query binds its values: `pg_audit_view_condition()` gives the filters as `?` placeholders; `pg_audit_view_where()` (the API) writes the same values in with `pg_db_bind()`. A `?` inside a value never breaks a `db_q()` template.

## How is it sorted and searched?
- Heading links (`pg_list_state('view_audit_log')`): date (default, newest first), who (`username`), action, record (`label`), IP; ties by `id` in the first level's direction. The sort menu sits above the table, outside the filter form (it has its own GET form).
- The search box: every word in the user name, record name, IP, request id, the stored action/type words, the shown source/action/type names (`pg_audit_view_labels()`), or the record's number (`#12`). The "Record name" filter and the CSV export are unchanged; the export still goes newest first by id.
