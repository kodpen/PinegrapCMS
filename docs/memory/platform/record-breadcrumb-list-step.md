---
name: record-breadcrumb-list-step
description: The middle step of a post's BreadcrumbList JSON-LD: the list page that links its records to the record page (pg_record_list_page(), #2181), how one is picked, when there is none
module: seo
---

## What does a post's BreadcrumbList say?
- Home > list page > post, written by `get_page_content()` with the BlogPosting block for a resolved record (legacy form item view, or a designed page whose record view widget found one). Not in e-mail or edit mode, silent with structured data off (`structured-data-open-graph.md`).

## Where does the list page come from?
- `pg_record_list_page($page_id)` (`includes/fn/seo.php`): a classic form list view (`form_list_view_pages`, collection a) whose `form_item_view_page_id` is the record page, and every page carrying a list widget (`pg_sw_form_list_types()`) whose `detail_page_id` is it (`pg_sw_widget_pages()`).
- `pg_record_list_page_pick()`: public, not archived, not noindex, not the record page itself; a page other than the home page first, then one in the site map, then the oldest. Named by its title, else its name; its address is PATH + its name.
- None fits, or only the home page: no middle step.

## What did it replace?
- #2181: the step was the first part of the pretty URL (`PRETTY_URL_PATH` up to the first `/`), which is the record page's own name (`blog-detay`), not the list (`blog`); a post opened at the root had no step at all.

Related: docs/memory/platform/structured-data-open-graph.md
