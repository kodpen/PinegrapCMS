---
name: style-complete
description: pg_style_complete.js: smart completion in the designer code editors (attribute names and values, CSS values, var(--…) tokens), no duplicate options, tests
---

## What does it add to the code editors?
- In every HTML editor of the designer page (code view, file tabs, the dialog) and its CSS regions (`<style>`, `style=""`), from the element around the cursor read off the syntax tree (`tagContext()`: tag, classes, attributes, declarations, six parents):
- HTML: attribute names (`P.attributes()`: a modal footer's button gets `data-bs-dismiss` first) and attribute values (`P.attributeValues()`: `data-bs-toggle="` offers `modal`…). `class` has its own source (`_sdCodeClassCompletion()`), `on…` is script, a value with `{{` is a binding.
- CSS: in `var(--` the tokens with their colour; at a value what the engine lacks (a length with its unit, a colour, `var(--token)`, the design's and the document's own custom properties); the engine's properties and keywords ranked by `P.properties()` / `P.values()`. Inside `calc()`, `url()`… the engine decides. Selectors are not offered yet.

## How is an option not listed twice?
- The engine filters an option whose label, type, detail, apply and boost equal the one before it. An attribute name is offered as the engine's own shape (`{label, type: 'property'}`), and the ranker gives both copies the same boost by label, so one is shown. A value is added only when it is not a plain keyword (`engineHas()`): the engine already lists every keyword and named colour at a value, which the ranker moves up instead.
- Boosts: the predictor's first 20 get 20, 19, … (`RANKED`); rankers cache their map per document and position.

## How is it loaded and wired?
- Lazily with the predictor: `PgStyleSuggest.configure({ complete: url })` (`includes/designer_screen.php`) and `load()` adds the script. `PgStyleSuggest.installCode(PgCodeEditor, { design })` registers two sources and two rankers that answer nothing (and start the load) until both are there; `style_designer.js` calls it beside the class and binding completion with `_sdCodeDesignContext()` (the tree's class counts and the tokens User Styles declare).
- Pure: no page access, every function takes the predictor first; `window.PgStyleComplete`, `module.exports` under Node.

## How is it tested?
- `tests/style_complete_test.php` runs it with the real predictor and vocabulary in the facade's completion slots on the bundle (Node; skipped without it). `tests/style_suggest_test.php` covers the delegates and the load. Drawing the list needs a browser and is not tested.
