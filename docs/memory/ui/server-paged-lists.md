---
name: server-paged-lists
description: Lists the server searches and pages (list table driver): which moved off DataTables' server paging, what the search box finds (words, labels, Turkish I), the row cells
module: list_paging
---

## Which lists are paged on the server?
- Pages (#6114), users (#6325), contacts (#6347), submitted forms (#6366), commissions and the mail queue (#6380), the Site Log, audit and firewall log (#6386) and products (TD7b) are on the list table driver (`ui/list-table-driver.md`, `ui/list-table-driver-lists.md`); it asks `list_table_rows` (`includes/panel/lists.php`) per 100 rows.
- DataTables' own server paging (`data-pg-list-action`, `pgListServerOptions()`, `pg_list_rows_answer()`, the page window and `LIMIT` helpers) is gone: its last user, the bookings agenda's list view, prints the month's bookings in browser mode since TD14a (`pg_booking_agenda_list_columns()`/`_list_row()`, list `view_bookings`).

## What does the search box find?
- Every word (a "quoted phrase" is one word, `pg_list_search_terms()`) somewhere in the row's text columns, case-insensitive. `pg_list_search_where()` writes `LIKE ?` per column with bound patterns (`pg_list_like_pattern()` escapes `%`, `_`, `\`); a column showing a label for a stored value (the role) matches by label (`IN (?)`). Times and check marks are not searched.
- Turkish (`tr` panel or page language, `pg_list_search_turkish_charset()`: the connection's `utf8`/`utf8mb4`; `term` columns never): a word with I or ı adds `CONVERT(expr USING cs) COLLATE cs_turkish_ci LIKE ?` (PR #3677). A word with any i letter adds `REPLACE(REPLACE(CONVERT(expr USING cs), 'İ', 'i'), 'ı', 'i') LIKE ?`, the word folded by `pg_list_search_fold()` (İ, I, ı → i, lowered; `ws_fold()`'s rule), so "yildiz" finds "Yıldız" (PR #3763, decision #3742). The plain LIKE stays (ş/s, IPHONE→iPhone). Labels match by `mb_stripos()` or folded.
- A list printed whole searches in the browser instead (`pgListTurkishMatch()`, `ui/list-table-driver-browser.md`).

## What must a row keep?
- A cell is `pg_list_cell($html, $class[, $attributes])`, escaped where it is built; `pg_list_row_html()` prints a row of them. The edit link's `send_to` is the screen with its filter, not `api.php`.

Related: docs/memory/ui/list-and-table-screens.md
