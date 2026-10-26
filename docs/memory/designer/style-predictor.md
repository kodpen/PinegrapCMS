---
name: style-predictor
description: The smart completion predictor assets/js/pg_style_predict.js: its API, the context object, suggestion fields, reason codes, how classes are ranked, the hand-kept hint tables, tests
---

## What is it?
- `assets/js/pg_style_predict.js` (`window.PgStylePredict`, `module.exports` under Node), plain ES5, served raw, no document access. `PgStylePredict.create(vocab)` takes the parsed `designer_vocab/bootstrap5.json` ([[designer-bootstrap-vocabulary]]) and returns the predictor; `create(null)` gives empty lists.
- Deterministic and text-free: it returns reason codes; `style_designer.js` maps them to `_sdT()`. Loaded lazily with the vocabulary (step 4).

## Which methods does a predictor have?
- `classes(q, ctx)`, `properties(q, ctx, extra)` (`extra`: more property names, e.g. the engine's list), `values(property, q, ctx)`, `tokens(q, ctx)`, `selectors(q, ctx)`, `attributes(q, ctx)`, `attributeValues(attr, q, ctx)`, `jsClasses(q, ctx)` (`bootstrap.Modal`), `jsMembers(className, q, isStatic)`, `jsEvents(q, ctx)` (`shown.bs.modal`), `components(ctx)` (`{ modal: weight }`), `slot(class)`. `PgStylePredict.match(label, q)` is the match score.

## What goes in ctx?
- `tag`, `classes`, `attrs` (`{name: value}`), `styles` (`{property: value}` already set), `parents` (nearest first, `{tag, classes}`), `design` (`classes: {name: count}`, `tokens: {'--x': value}`, `ids: [{id, classes}]`), `limit` (default 15). All optional.

## What does a suggestion hold?
- `label`, `score`, `codes`; where they apply `replace` (class to use instead), `conflicts` (classes on the element it overrides), `bp`, `decl` (a class's declarations), `swatch` (resolved CSS colour), `component` (jsClasses).
- Codes: classes `ctx` `state` `family` `tag` `design` `custom` `bp` `redundant` `conflict` `fixed`; values `bs` `unit` `token` `design`; tokens `token` `own` `ctx` `design`; selectors `own` `ctx` `pseudo`; attributes `component` `tag` `common`; values `ctx` `component` `option` `known` `design`; JS `ctx` `component` `method` `static`.

## How are classes ranked?
- Match: exact 1000, prefix 600, segment start (`flex` → `d-flex`) 350, contains 100, minus length. An empty query lists only what the context points at.
- Plus: ancestor in the class's `ctx` 220, a part of the direct parent (`card` → `card-body`; not a variant that sets `--bs-<parent>-*` or has no rule) 160, `row` → `col*` 200 (scaled 1 / 0.6 / 0.3 by depth); a state of a present class 150; a variant of a present class 120; tag hint 100; design use up to 90; a responsive variant of what the element sets 80.
- Minus: an override (same `slot`: breakpoint + declared properties) 150, a responsive copy of a present class's exact rule (`d-md-none` beside `d-none`) 200, a fixed colour 300 with `replace`. Present classes are never offered. Ties: shorter label, then name.

## Which knowledge is hand-kept, not read from the CSS?
- `TAG_CLASSES`, `PARENT_CHILDREN` (row → col), toggle hints (`TOGGLE_BY_CLASS`, `TOGGLE_BY_PARENT`, `TOGGLE_VALUES_BY_PARENT`, `TOGGLE_PRIOR`), `ATTR_VALUES` (slide, placement…), `COMMON_ATTRS`, `TAG_ATTRS`, `PSEUDOS`, `UNITS`. Attribute rules: a component's own element gets its option attributes, a trigger `data-bs-toggle`/`target`, an element inside a component `data-bs-dismiss`; Collapse's `toggle` option never adds true/false to `data-bs-toggle`.

## How is it tested?
- `tests/style_predict_test.php` runs it under Node on the shipped vocabulary (skipped without Node).
