---
name: record-tree-lists
description: Help centre HC9: list sources "Records that point at the record" (a category tree in one widget) and "Path to the record" (breadcrumbs), what they read and show
module: widgets_lists_tree
---

## What do the two lists give?
- Two list sources of the form record family, in Form List View and Form Item View (`pg_sw_list_tree_sources()`, `includes/fn/widgets_lists_tree.php`), both with a required field arg and the row tokens `__record_title` (Title field, else the code), `__record_url` (the record's address on its record page), `__record_code`.
- `form_record.children`: the records whose reference field of that name, in this form or ANY form pointing into it, names the row's record by code or address name (several names joined by `, ` count). Parent lists the sub-categories, Category the articles.
- `form_record.crumbs`: the records above the record, root first, through the field of that name in its own form, then in each parent's form; at most 10 levels; a loop in the parents ends the path. The shown record itself is not a row: the design adds it after the list.

## How is a tree drawn in one widget?
- Each row also carries `submitted_form_id` (its own id), the family's key, so a children list inside a children list's row lists the level below (lists nest four levels, `widget-lists.md`). The main loop gives the top level: a record view of a root category, or a list whose rows stand under a condition on the Parent field being empty.
- Starters: children `ul.list-unstyled.ps-3 > li > a`, crumbs `ol.breadcrumb > li.breadcrumb-item > a`, both bound to `__record_url` and `__record_title`.

## What may a visitor see through them?
- Only records their form's public record page shows: `pg_ap_record_page()` (standard page, public folder, a record view open to everyone not comparing the viewer) and that view's filters (`pg_sw_record_filter_clause()`). A form with no such page, a draft, an incomplete record or a reference field kept for office use gives no row.
- Unlike the other form record sources they read the database (`pg_sw_list_tree_source_rows()` from `pg_sw_list_source_rows()`): children one query per field and list level for every key of the level, crumbs one per level; at most 500 rows per read. Titles print as stored, untranslated, as references do (`collection-references.md`).

## What does the editor do?
- `SW_LIST_SOURCES` twins, palette items under the lists, about texts, samples. A crumbs list's field select offers the form's reference fields; a children list's field is typed (`.sd-list-arg` text input), since it may be a field of another form, and its band does not turn amber for a name the widget's form lacks.

## What is open?
- No order by an "Order" number field: rows stand in record order, or A to Z / Z to A by title.
- Tests: `tests/widget_lists_tree_test.php` (rows, children, path). The reads against a database and the designer in a browser were not run in the cloud session.

Related: docs/memory/plans/help-centre.md, docs/memory/designer/widget-lists.md, docs/memory/designer/collection-lists.md
