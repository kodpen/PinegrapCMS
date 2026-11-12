---
name: designer-assistant-apply-error
description: Plan (owner 2026-11-12, #8970): the designer Assistant's Apply answered with a PHP message instead of JSON; reproduce, fix the cause, JSON answers under errors, a readable message; steps DA1-DA3
---

## What happened?
- Owner (2026-11-12): "claude a sayfada bir alanı yapmasını söyledim uygula çalışmadı,". The proposal came (six changes: a background picture with a dark overlay on a section, a new badge, heading, text and button); "Uygula" showed twice `Unexpected token '<', "<br /> <b>"... is not valid JSON`. Issue #8970 holds the screenshot's reading.

## What is known?
- "Uygula" posts `ai_apply` with the proposal's id and the page tree as `tree_json` (`assets/js/designer_ai.js`, through the editor's bridge); the answer comes from `pg_design_ai_panel()`, `pg_design_ai_apply_to_editor()` and `pg_design_ai_apply_ops()` (`includes/designer_ai.php`).
- The answer began with PHP's printed message (`<br />`, `<b>`): a warning or a fatal error while the changes were applied, or the request refused before the script ran (a posted tree over the server's post limit prints in this form). Only the raw answer tells which.

## Which steps?
- DA1 (S) Reproduce and fix the cause. A sandbox with errors shown; a proposal of the same shape on a section (build the proposal row by hand if the agent cannot be reached); capture the raw answer of `ai_apply`. Fix what it names, with a test that is red first on the function it names. If the cause is the size of the post, say in the PR what the tree weighed and propose the remedy to the architect before building it.
- DA2 (S) JSON under errors: an Assistant action never answers with PHP's text. Find how the editor's other actions guard their answer and use that way; a PHP message during an action is logged and the answer is a JSON error with a sentence. No second way where one exists.
- DA3 (S) The panel's words: when an answer cannot be read the Assistant says once, translated, that the change could not be applied because the server answered with an error, never the browser's parse text, and leaves the proposal open to try again. One message for one click.

## Which rules?
- No schema. The script's source and its served bundle together (`php tools/build_js.php`); texts through `lang()` or `_sdT()` with `tools/tr_add.php`.
- DA1 and DA2 may be one PR if small; DA3 its own. Each PR says what could not be reproduced.
- Claim as `[plan:designer-assistant-apply-error] <step> — <topic>`. Close-out: the cause and the guard into `designer/`, this plan deleted.
