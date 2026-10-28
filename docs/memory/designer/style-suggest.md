---
name: style-suggest
description: assets/js/pg_style_suggest.js: the lazy loader of the predictor and vocabulary, the element context, the light dropdown, the Attributes class field and the code view's class list on it
---

## What does it do?
- `window.PgStyleSuggest`, the only smart-completion file the designer page carries (`includes/designer_screen.php`, which also calls `configure({ predictor, vocab })` with stamped URLs).
- `load(cb)` fetches `pg_style_predict.js` and `designer_vocab/bootstrap5.json` once, on the first focus of a class input or the first class completion in the code view; every caller gets the same predictor. A failure is final for the page and answers `null`; the inputs keep working with the design's own classes.
- `elementContext(el, { skipClass, skipEl })` reads tag, classes, attributes (not `class`, `style`, `data-sd-*`, `data-sw-*`) and six ancestors up to `<body>`; the designer passes `/^sd-/` and skips `.sd-wrap`. `designClasses(lists)` counts classes.
- `dropdown(input, { items, pick, onFocus, openEmpty })` is the light dropdown (`.sd-class-ac-*` CSS): never preselects, so Enter on a typed value still adds what was typed; arrows, Tab, Enter on a highlighted row, click, Escape.

- `installCode(PgCodeEditor, opts)` registers the code editors' smart completion (html, css, javascript sources; html, css rankers); `configure({ complete })` names the module `load()` fetches with the predictor ([[style-complete]]).

## How does the class field use it?
- `_attachClassAutocomplete(input, node, pick)` in `style_designer.js`: context from the selected element on the canvas (another node of a multiple selection from its props), plus `design.classes` from the tree. With nothing typed it lists what the context points at (`col*` in a `row`). A pick adds the class at once (`addClassToken` / `_addMultiClass`) and focus moves to the redrawn input.
- `_sdClassHint(item)` turns the predictor's codes into one short `_sdT()` reason; `fixed` and `redundant` rows are dimmed.

## What replaced class_suggestions.js?
- The fixed list `window.PG_CLASS_SUGGESTIONS` is gone (step 4, 2026.11.0); `clean_up.php` deletes the file on updated sites. The code view's class completion uses the predictor's full match list (`limit: 5000`, the editor filters while the word grows) once loaded.

## What stays out of it?
- No heavier designer page: predictor and vocabulary load on first focus or first editor; plain panel inputs never load CM6.
- The inline hint (planned off by default) and a User Styles CSS tab are dropped until a site asks (#2439).

## How is it tested?
- `tests/style_suggest_test.php` (Node, plain stand-ins): context, counts, load once/failure, the hint text on real predictor results. The dropdown draws into the document and is not unit tested.
