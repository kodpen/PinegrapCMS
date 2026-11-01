---
name: form-view-newest-comment-cut
description: ^^newest_comment^^ in classic form item / list views and content variables is cut to 100 characters before escaping, never after
module: forms
---

## How is ^^newest_comment^^ shortened?
- `pg_form_view_newest_comment()` (`includes/fn/forms.php`) keeps the first 100 characters of the raw message and adds `...`. `get_form_item_view.php`, `get_form_list_view.php` and `get_variable_submitted_form_data_for_content()` (`includes/fn/content.php`) call it in the `standard` case, before `prepare_form_data_for_output()` escapes and `nl2br()`s the text. The designer widget (`includes/fn/widgets.php`) already cut before escaping.

## What did it fix?
- The three classic renderers cut the escaped text: an `&` near character 100 printed `&am...`, and a line break there left an open `<br` tag that swallowed the layout markup up to the next `>`. `tests/form_view_newest_comment_test.php`.
