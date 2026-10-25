---
name: comments-widget-save-warnings
description: The designer's save warnings about a page's comments that are not drawn (fixed block plus widget, two widgets, nested record widget, comments off): census, approximation, codes
---

## When does the save warn?
- `pg_designer_save_page()` (dry run and write; the editor toasts the dry run's) adds `pg_comments_save_warnings($tree, $comments_on, $name)` for a page whose tree validated, not one sent as unchanged. Never refuses.
- `pg_comments_tree_census()` (refs per id, comments_block regions; a placement is not walked into), `pg_comments_component_census()` (a query a level, 8 levels, 500 rows, each id once), `pg_comments_page_census_facts()` -> widgets, blocks, nested_record, top_record. `pg_comments_page_nested_record()` shares reader and decision (cache, result unchanged).
- Approximation: a component is read once (a header with the widget, placed twice, counts one).
- `pg_comments_save_warning_codes()`, in order, only with a widget: `block` (the widget claims the page), `widgets` (only the first draws), `nested_record` (a form_item_view only below the top level: fails closed), `off` (comments off). Texts: `pg_comments_save_warning_text()`.

## How was it checked?
- `tests/comments_widget_designer_test.php`: tree census, census facts, warning codes and texts, the save wiring. No running instance.
