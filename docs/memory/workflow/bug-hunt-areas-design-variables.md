---
name: bug-hunt-areas-design-variables
description: The bug hunt in a design's variables (2026-11-26, PR #11770): the Variables tab, the start questions, "Ask me again", Export and Import; what was read, fixed, seen and left
---

## Which area was it?
- A design's variables: `includes/fn/design_variables.php`, the parts `variables` and `questions` (`assets/js/style_designer_variables.js`, `assets/js/designer_questions.js`), the `designer/variables_state` sub-action and the save in `designer_screen.php`. Not the Publish check (CU2, #11668).

## What was fixed?
- A typed value the model refuses (Base size "18", "2em", a number out of range, a font with an open quote) is caught in the field: red, with the row's bounds as its title ("From 0.75rem to 1.5rem, or from 12px to 24px."), and not sent. Before, the server put the default in its place without a word, and an own size with a refused value was dropped with every place that read it.
- A bare 0 is refused where the row does not start at 0. Helpers `_sdVarsTypedOk()` / `_sdVarsTypedHint()`, twins of `pg_design_variable_value()`.
- "Remove" on an own colour or size that elements read asks first ("“Sand” is used in 3 places. They lose its value."), as a curve's did. A bound control holds `var(--pg-var-c-…)` with no fallback, so before they lost the value silently.
- A late answer of `variables_state` to an older change no longer overwrites a newer change in the hidden input `style_variables` (a Save in that moment lost the newer change).
- Tests: `tests/design_variables_tab_test.php` (helpers and the change flow under Node).

## What was read and found clean?
- The stylesheet: colours pass `_pg_theme_rgb()`, lengths and numbers fixed patterns, the head's JSON keeps `/` escaped, the name line goes through `pg_design_theme_comment_text()`; custom keys are lowercase slugs, so two names by case are one key; built-in and own tokens never meet (`--pg-var-c-`).
- Import: the size limit in the browser and the server, the four refusals, references followed eight steps, the walk stops at depth 12 and 2,000 tokens; the report's counts are taken from the normalized model.
- The start questions and "Ask me again": answers are checked by `pg_design_variables_answers()`; skipping all five writes nothing; own variables stay.
- Rights: `variables_state` writes nothing and sits behind the `designer` action's token and role gate; the file is written only by the design save.

## What was seen and left?
- Held by #11755 (same file) and kept for a follow-up: in `pg_design_variable_value()` a bare `0` passes every length row, so a posted or imported Base size 0 writes `--pg-font-size: 0` and hides the text of every page (the tab now refuses it, the server does not).
- Also held by #11755: a font name with an open quote (`"Inter`) passes and swallows the next declaration of the stylesheet.
- Also held by #11755: in the import, an own token of another kind than the design's own variable of that key turns it into that kind; a token under `own` whose key reads as a built-in name (`size-base`, `border-width`, `muted`) sets the built-in on a round trip.
- Two people saving the Variables tab at once: the last save writes the whole file; the design save itself has the same rule.
- The head is not held to `PG_DESIGN_VARIABLES_HEAD_MAX`: 24 own variables with names of escaped characters could pass the 8,256 bytes `pg_design_variables_stored()` reads, and the tab would show the defaults.
- Curves are exported (`cubicBezier`) but an import leaves them out as "easing".
