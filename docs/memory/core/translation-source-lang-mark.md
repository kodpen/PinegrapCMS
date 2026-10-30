---
name: translation-source-lang-mark
description: A node drawn untranslated on a page in another language carries lang="<source>" so the browser applies the source language's letter rules
---

## What is drawn?
On a page in a target language, an element whose own words have no translation gets `lang="<source language>"` (`lang="tr"` on a Turkish site's `/en/`). Translated texts, values that are not texts to translate (`pg_tr_translatable()`) and pages in the source language get nothing.

## Why?
The document is `lang="en"` on `/en/` (`pg_tr_render_finalize()`), and the forum template's kickers and labels are `text-transform: uppercase`: an untranslated Turkish "Rehber kılavuzu" turned `i` into `I`, not `İ` (issue #3552, PR #3640). The canvas had the same symptom and got `<html lang>` ([[designer-inline-text-editing]]).

## How does it work?
- `pg_tr_render_text()` reports through its fourth (by-reference) argument whether a text was `translated`, `missing` (a text to translate, drawn as written) or `skipped`.
- `pg_tr_render_props()` hands the per-field states to `pg_tr_render_lang_mark()` (`includes/translate/render.php`), which sets the internal prop `_tr_lang` when a content field is missing and none is translated. Content fields only: `alt`, `_attrs:…`, `_cond:…` and the `_label…` captions do not count.
- `_render_tree_node()` passes the props through `pg_tr_lang_attr_props()` (`includes/fn/translate.php`): the mark becomes a `lang` entry of `_attrs`, drawn by `_render_extra_attrs()` with the node's other attributes, and is removed. A `lang` the user set in the designer wins.
- Tests: `tests/translate_lang_mark_test.php`.

## What is not covered?
- Database texts swapped in by `pg_tr_render_db_texts()`: no element of their own.
- The designer preview draws only the source language, so it has no twin.

## How did running sites pick it up?
`pg_tr_render_rules()` went to `2026.11.0-2`: the drawn bodies are dropped once and the kept "index" answers stay (`pg_tr_render_rules_check()`).
