---
name: designer-records-site-search
description: How the search index finds records of visual-editor pages: qualifying lists, item fields and folder, no record page items, failure isolation (#1197)
---

## What did it fix?
Site search found no record of a visual page (only legacy list pages were); the bare record page was indexed as its not-found text.

## Which records get a search item?
- `update_search_index.php` collects the list widgets (`pg_sw_form_list_types()`) of each page it indexes, then writes one item per record after the page loop, before files (as the sitemap's designer branch).
- Detail page: `standard`, its "Include in site search" on, folder not archived or binned. Record view: its first `form_item_view` over the list's form.
- `pg_sw_search_index_eligible()`: both widgets public, no `viewer_filter`, no filter on the viewer (with no visitor it matches every anonymous record).
- Records: `pg_sw_list_record_filter_clause()` (record view's filters strict and fail closed, then the list's), `forms.complete = 1`, a reference code.
- Before drawing, `pg_sw_record_comment_context($detail, $id)['visible']`; after, `pg_sw_item_missing()`. Either fails: no item.

## What does an item hold?
- `url`: `pg_sw_search_index_record_url()`: pretty address, else `?r=` (over 255 bytes: none).
- `title`/`description`: `pg_sw_search_index_record_texts()`: the record view's `title_field`, else the RSS title fields, no `title_suffix`; the first RSS description, 255 + `...`. Empty title: the detail page's.
- `content`: title, description, the detail page's keywords, the drawn text (not its title/meta description).
- `page_id`: the detail page (legacy: the list page). `folder_id`: `pg_sw_search_index_folder()`: the detail page's when the list page's folder is open in site search (`pg_sw_search_index_folder_open()`: public, registration, guest), else the list page's, so a staff or draft list reaches only its own audience. A record reached by several lists is drawn once; a later list on an open page widens its folder.

## Why is a record page not an item itself?
A page with a `form_item_view` is skipped in the page loop (without `?r=` it is its not-found text), even with other content (intro, a list): only its records are items. Text to be found goes on a page without a record view. Keep its search switch on; it gates its records.

## How is each record drawn?
`get_page_content($detail, ..., array('form_id' => id))` with `$_GET['r']` set: the widget reads `?r=`, the head `form_id` (`?r=` alone gave every record the first one's title). Each record, and each list's query, runs under `pg_error_throws(true)` in `try`/`catch (Throwable)`/`finally`, restoring `?r=`, the flag and the output buffer level (a throwing PHP region leaves one open). A failure is counted (one `error_log` line); no item, the sweep drops the old one. `$_GET['page']` is never set. A panel-button run reads records anonymously: other `logged_in`/`submitter_only` record views count the record missing; comments are published only. Per-record caches (`pg_sw_record_comment_context()`, `pg_sw_page_item()`: by `?r=` too; `pg_sw_record_passes_filters()`) clear at 256.

## How fresh is it?
- Daily job (lock 4 h: a second run's sweep would delete a long first run's items) or the panel button. Nothing re-indexes on save; a retracted record stays until the next run.
- Deferred: incremental re-index (many status writers, no record id column, a save-time render runs as the editor); maybe an hourly job over `forms.last_modified_timestamp`.

## What else is out of scope?
Simple mode (starter dumps are Advanced; the settings help says so); translated items; result type/image; record views in a shared component; catalog/calendar detail pages; a designer list over a legacy detail page.

## What should templates do?
Article page `search` on, form RSS `title`/`description` roles, publication filters on record view and lists.

## Which tests cover it?
`tests/designer_search_index_test.php`: helpers, source checks, job lock, cache bounds.
