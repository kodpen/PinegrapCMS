---
name: api-console-try-fields
description: API documentation Try it fields (fieldsFor() in includes/api/console_view.php): each field gets an id from a page-wide counter and its caption is a <label for>
---

## What was wrong?
- Settings › API documentation and the outside console build an endpoint's Try it fields in `fieldsFor()` when its panel opens, long after page load. The caption (`limit query`) was a `<label>` with no `for` and the field had no id. The panel's DOMContentLoaded label binder (`backend.src.js`) never sees these fields, so text boxes had no name, list boxes were read as their placeholder `["a", "b"]`, and clicking the caption did nothing.

## How are they named now?
- `var fieldSeq = 0;` sits outside `fieldsFor()`. Each parameter row takes `id = "doc_field_" + (++fieldSeq)`, carried at the start of the shared `attributes` string (textarea, both selects, input), and the caption is `<label for=id>`. The `where` span stays inside the label, so the name is the visible text.
- One counter for the page: several panels can be open at once, parameter names repeat across endpoints, and `openPanel()` rebuilds the markup each time.

Test: `tests/api_console_try_fields_test.php`.
