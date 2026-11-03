---
name: find-and-replace-results
description: What find_and_replace.php searches (no orphan or binned page regions, no binned products) and how its Find Results screen names, counts and caps the records
module: text_search
---

## Which rows are left out of the search and the replace?
- Page Regions join `page` with an INNER JOIN and leave out pages in the Recycle Bin (`pg_pages_bin_sql(pg_recycle_bin_folder_ids(), false)`, as `mass_edit.php`). Before, a row whose page was gone linked to `/` and binned `pgbin_…` pages were listed and changed (#5882).
- Products and product groups leave out the bin with `pg_catalog_bin_live_sql()`; Edit Product opens a binned product read-only, so this screen must not write it.
- The same `extra_join` and `base_where` serve Find and Find & Replace (`find_replace_rows()`).

## How is a record shown?
- Field column: the definition's `column_labels` (translated, e.g. Short Description, Content), never the raw column name; submitted forms show the field's own name (`column_label_from => 'name'`).
- Name: a page region shows its page name (`display_name_column`), as its region name is an internal id.
- Submitted forms store one `form_data` row per field. `record_key => 'form_id'` merges a submission's rows into one record (`pg_find_replace_merge_records()`), named by the custom form name and reference code; the badge counts submissions, not fields (#5884).
- The snippet ends with "…" only when text follows the shown context (`pg_find_replace_snippet_cut()`).
- Each type lists its first 200 records and says how many more are not listed; a one-letter search had returned 3.6 MB of HTML.
- The results table carries `pg-find-results`, which removes the `.table` top margin inside the card.

Tests: `tests/find_replace_rules_test.php`.

Related: docs/memory/platform/find-and-replace-architecture.md
