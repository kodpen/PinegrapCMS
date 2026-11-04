---
name: mass-edit-live-pages-and-input-vars
description: Pages › Mass Edit reads only live pages and posts only changed fields, with a max_input_vars marker
---

## Which pages does Mass Edit (`mass_edit.php`) read?

Only live ones. `$live_pages_where` (`WHERE 1 = 1` + `pg_pages_bin_sql(pg_recycle_bin_folder_ids(), false)`)
is appended to the list, the page count, Find & Replace and the save loop.
Before, Recycle Bin pages (`pgbin_<id>_…`) were listed and Find & Replace
renamed them, so a restore brought a page back under a name nobody chose
(#4351). Pages in archived folders are still listed, as before.

## Why can a save not lose the pages at the end of the list?

The form posts one input per page and chosen field; past PHP's
`max_input_vars` (default 1,000) PHP drops the rest and the loop skipped
those pages in silence (#4350). Now:

- On submit, the screen's script disables every unchanged field (text by
  `defaultValue`, the search switch and its hidden `0` by `defaultChecked`),
  so only changes are posted; `pageshow` enables them again.
- `max_input_vars_test` is the form's last field; a post without it stops
  with an error before any write, as `add_product_group.php` does.

## How many pages does the edit grid draw?

One screen of 50 (`MASS_EDIT_PAGES_PER_SCREEN`, `?screen=N` read by
`pg_list_page_number()` and held by `pg_list_page_clamp()`), with the
columns the grid uses. The form posts `screen` and the save comes back to
it. Before, all live pages were drawn at once (683 cards, 3 MB of HTML, a
chips field and two counters each) and the tab froze for minutes.

## Does a Mass Edit save show as a page change?

Yes. A changed page gets `pg_page_bulk_touch()` (`page_timestamp`,
`page_user`) once, before its audit row, in the grid save and in Find &
Replace, and each submit writes one site log line ("Mass Edit: …" with the
count lines). Before, the Pages list's Last Modified column and its default
sort did not show these edits and the site log had no line.

Tests: `tests/mass_edit_live_pages_test.php`.
