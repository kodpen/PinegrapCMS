---
name: style-fields
description: Smart completion in the designer's panel inputs: pg_style_fields.js (caret part, pick splice), the Attributes rows and Styles panel fields wired to it, when a list opens, how a pick is written
---

## What does assets/js/pg_style_fields.js do?
- `window.PgStyleFields`, pure, carried by the designer page beside `pg_user_styles.js`. `suggest(P, spec, text, caret, ctx)` asks the predictor ([[style-predictor]]) for the part of a field under the caret and returns `{ from, to, part, items }`; `apply(text, res, label)` gives the text and caret after a pick.
- Kinds: `property`, `value` (`name`: the property), `selector`, `attribute`, `attrValue` (`name`: the attribute), `style` (a declaration list). One-thing fields are replaced whole; a value only in the word under the caret (words end at space and comma, `var(--bs-pri` is one word).
- `declAt()` splits on `;` and new lines outside quotes and brackets; while a bracket is still open brackets are not counted, so `var(--bs-bor ;x: y` keeps `x`. A property picked in a style list gets `: ` (an existing colon is reused).
- A value of several words (`var(--bs-border-width) solid …`) is offered only while the first word is typed and replaces the whole value.
- `cssTokens(css)` reads the User Styles custom properties for `ctx.design.tokens`.

## Which inputs use it?
- `_sdAttachFieldSuggest(input, spec, opts)` in `style_designer.js` (beside `_attachClassAutocomplete`), on `PgStyleSuggest.dropdown()`. Context: `_sdClassPredictCtx()` plus `design.tokens`, `design.ids` (`_sdTreeIds()`) and `styles` (the other declarations). `_sdFieldHint()` maps codes to `_sdT()`.
- Attributes panel (`_sdAttachAttrsSuggest`): custom attribute name and value, the style row (and the virtual one) as `style`. Styles panel (`_sdAttachStylesSuggest`): element.style and User Styles rows and add rows, and each rule's selector. The custom style textarea ([[custom-style]]) as `style`; there Enter is a new line, the write comes on blur.
- Neither attaches when `_sdPanelLockReason()` locks the card; writes go through the fields' own handlers (`_sdMayEdit`, one `saveState()`).

## When does a list open and how is a pick written?
- An empty field lists on focus; a filled one only after typing, then it follows the caret (arrows, Home/End, click).
- Attached before the rows' own handlers: Enter on a highlighted row picks first. The Attributes style row's Enter handler skips a picked Enter; the Styles add rows add what Enter picked.
- Fields that write on `input` (attribute name and value) get an `input` event. Fields that write on `change` get one on Enter or blur after a pick (a value set by script never fires `change`). A property picked in an existing row is written at once and the caret moves to its value.

## How is it tested?
- `tests/style_fields_test.php` (Node, shipped predictor and vocabulary): caret parts, tokens, each kind's list and splice, `_sdFieldHint()`. The dropdown wiring is DOM work, not unit tested.
