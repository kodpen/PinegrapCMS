---
name: not-found-log-screen
description: The 404 log screen (view_not_found_log.php): rows and their state, the search in the stored form with the Turkish rule, wrapping cells, Create redirect
module: not_found_log
---

## What does the screen do?
- `view_not_found_log.php`, managers and up (redirects are short links), in the Pages menu after Create Short Link and as a view switcher beside the short link list.
- Rows: most requested first, search, who asked, sort, first 1000. The search is read in the stored form (`pg_short_link_name_from_address()`: `/ığdır` as shown, a pasted full address) and matched by `pg_list_search_where()` with the Turkish I/ı rule (#5792). The address, its note and the referrer wrap in a bounded `div` (`table.chart` cells are `nowrap`; a long address pushed the counts off screen, #5791). State from `pg_not_found_log_answers()` (`pg_short_link_keys()`, every name of a key): a short link or a `*` pattern answers it (edit), one in the Recycle Bin holds the name, a page or file answers, the first part is a language directory (a note, no button), or Create redirect.
- Create redirect opens `add_short_link.php?name=…&redirect_code=301` (`pg_not_found_log_redirect_url()`): name and answer filled, the operator picks the destination, the usual checks on save.
- Delete a row or clear the log: POST with the token.

Tests: `tests/not_found_log_test.php`. PRs: #1420; the record side: `not-found-log.md`.
