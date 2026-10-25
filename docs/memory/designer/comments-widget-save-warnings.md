---
name: comments-widget-save-warnings
description: Save warnings about a page's comments not drawn (block plus widget, two widgets, nested record widget, comments off; an e-mail page only two widgets): census, approximation, codes
---

## When does the save warn?
- `pg_designer_save_page()` (dry run and write; the editor toasts the dry run's) adds `pg_comments_save_warnings($tree, $comments_on, $name, $page_id)` for a page whose tree validated, not one sent as unchanged. Never refuses.
- `pg_comments_tree_census()` (refs per id, comments_block regions; a placement is not walked into), `pg_comments_component_census()` (a query a level, 8 levels, 500 rows, each id once), `pg_comments_page_census_facts()` -> widgets, blocks, nested_record, top_record. `pg_comments_page_nested_record()` shares reader and decision (cache, result unchanged).
- Approximation: a component is read once (a header with the widget, placed twice, counts one).
- `pg_comments_save_warning_codes()`, in order, only with a widget: `block` (the widget claims the page), `widgets` (only the first draws), `nested_record` (a form_item_view only below the top level: fails closed), `off` (comments off). Texts: `pg_comments_save_warning_text()`.

## What does a comment e-mail page get?
- Only `widgets`: its widget draws the comment an e-mail names, whatever its own comments switch, block or record widget ([[comments-widget-email]]). Fact `email_page`: `pg_comments_is_email_page($page_id)`, one count of the pages naming it as their submitter or watcher e-mail page, asked only once the census finds a widget (not for every save); 0 for a page not saved yet, so a new e-mail page gets `off` once.
- No warning for a widget whose card has no link to the comment (every e-mail then sends the fixed markup): the canvas's E-mail state leaves it out with a note ([[comments-widget-email-design]]).

## How was it checked?
- `tests/comments_widget_designer_test.php`: tree census, census facts, warning codes and texts (e-mail page on the pure codes, no database), the save wiring and the order of the e-mail page check.
- Sandbox, MariaDB 10.11 (2026-10-25): an e-mail page with a widget and comments off warns nothing, a thread page `off`.
