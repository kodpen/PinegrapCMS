---
name: designer-assistant-apply-answers
description: Assistant Apply's "Unexpected token '<'" (class names sent as lists), what an operation's fields accept, how the Assistant actions keep their answer JSON
---

## What went wrong on Apply (2026-11-12)?
- Claude, through the external API, proposed six operations for the store template's campaign box; the first was `update` with `add_classes` and `remove_classes` as JSON lists. `_pg_dai_op()` cast them with `(string)`: PHP printed "Array to string conversion" twice and the box got the class `Array` instead of `pg-moda-hero`, so the page CSS of the same proposal never applied.
- With display_errors on (dev.pinegrap.com), the warnings sat in front of the editor's JSON (`<br />\n<b>Warning</b>: …`), so the panel showed the browser's "Unexpected token '<'". The row was marked applied all the same.
- The proposal was made without a sign: the API path runs behind `integration.php`'s buffer, which drops the warnings.
- Not the post size: the raw answer of `ai_apply` named `designer_ai.php` (`add_classes` / `remove_classes`).

## What does an operation's field accept now?
- `classes`, `add_classes`, `remove_classes`: one string of names separated by spaces, or a list of such strings (`pg_design_ai_class_words()`); a list becomes one string before the operation runs (`pg_design_ai_op_fields()`, first thing in `_pg_dai_op()`).
- Every other field that holds words (`op`, `node`, `parent`, `before`, `after`, `index`, `text`, `style`, `html`, `css`, `name`) must be a string or a number: a list or an object is refused with `<field>: a string, not a list or an object.`, so the API answers 422 with `ops[i]` and no proposal is kept.
- `pg_design_ai_bare_classes()` and the page_set tool read class lists the same way.

## How do the Assistant actions keep their answer JSON?
- The editor's `ai_*` actions (`includes/panel/designer.php`) and the Workspace's `ws_ai_design_*` run inside `pg_error_json_action()` (`includes/fn/errors.php`, rules in `core/json-endpoint-rules.md`).
- A reported warning or error, an exception, `output_error()`, an answer JSON cannot write, or a fatal error that ends the request turns the answer into `status: error`, `code: server_error` with "The server ran into an error, so this was not done. The details are in the Site Log."; the details are in `data/temp/php_errors.log` (Site Log).
- Left out: a memory-exhausted fatal. PHP throws the buffers away itself and prints its line straight out, so that answer still starts with PHP's text.

## How is it tested?
- `tests/designer_ai_op_fields_test.php` (red first on line 2528 of `designer_ai.php`) and `tests/error_json_action_test.php`.

Related: docs/memory/designer/designer-ai-known-class.md, docs/memory/core/json-endpoint-rules.md
