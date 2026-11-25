---
name: design-variables-files
description: Export and Import of a design's variables (DV5): the Design Tokens file written and read, the More menu rows, the question before a file is used, the reasons a file is refused
module: design_variables
---

## Where are Export and Import?
- Two rows of the Variables tab's "More" menu (Settings, Theme, "Variables"), after "Ask me again": "Import" and "Export". No button of their own. "Export" is grey while the design has no variables (no saved file and nothing tried on); the menu checks again each time it opens.
- The work is in the part `variables` (`exportTokens`, `importTokens` in `assets/js/style_designer_variables.js`), called by `_sdVarsFile()` in the core; the server is `designer/variables_state` with `export: 1` (and the design's `name`) or `tokens: <file text>`.

## What does the file hold?
- The Design Tokens community format, written by `pg_design_variables_tokens_export()` / `_text()` (`includes/fn/design_variables.php`): groups `color`, `type`, `spacing`, `shape` and `own`; a token's name is its key with `-` (`size-base`).
- Colour: `$type: color`, `$value` the light value, the dark one in `$extensions."com.pinegrap".dark`. Lengths are `dimension` as `{value, unit}`, fonts `fontFamily` (a list for a stack), weights `fontWeight`, scale and line height `number`. The shadow preset sits in the `shape` group's `$extensions."com.pinegrap".shadow`, only when not "As the look". An own variable keeps its name in `$description`.
- File name: the design's name made a slug, `<name>.tokens.json` (`design.tokens.json` with none).

## How is a file read?
- `pg_design_variables_tokens_import($text, $model, $defaults)`, pure. A token's last name picks the built-in variable (our names, and `primary`, `secondary`, `background`, `foreground`, `error`, `muted`, `border-radius`, `corners` ...) when its type fits; a colour without a dark value keeps the design's dark one.
- Any other colour or dimension comes as an own variable (key: its path made a slug, an existing key is rewritten), up to 24. Everything else is counted by kind as left out (gradient, duration, a font with no place, a reference that leads nowhere, over the limit). References `{group.token}` are followed; the 2025 colour object (`hex` or sRGB `components`) and `#rrggbbaa` (alpha dropped) are read.
- Every value passes the model's own checks (`pg_design_variable_value()`); the result is normalized.

## When is a file refused, and what does the person see?
- Reasons, in a red line under the tab row with the file's name and "Choose another file": "It is not a JSON file.", "It is larger than a tokens file can be." (over 256 KB, `PG_DESIGN_TOKENS_MAX`, checked in the browser too), "It is not a Design Tokens file: no entry in it has a "$value".", "None of its tokens is a colour, a size, a font or a number." Nothing changes.
- A read file is asked about in place of the folds: how many tokens, how many values change (per group), how many come as your own, how many are left out and of which kind; "Import" (filled) and "Cancel". Import puts the model in the hidden input, tries it on and shows the teal line "n variables imported from <file>."; Save writes the file.
- Export downloads at once and shows the teal line "n variables exported to <file>, in the Design Tokens format."

## What is tested?
- `tests/design_variables_tokens_test.php`: the format, a round trip giving the same model and changing nothing the second time, another tool's file, the four refusals, the limit of 24.

Related: docs/memory/designer/design-variables-tab.md, docs/memory/designer/designer-variables-drawing-questions.md
