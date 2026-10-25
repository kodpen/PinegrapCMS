---
name: code-editor-completion
description: Code editor completion (pg_code_editor.js): css/js sources inside an HTML editor, addCompletionRanker, colour swatches, the slot sources and the lezer lookup-cache reset
---

## Which sources fire where?
- `PgCodeEditor.addCompletionSource(language, source)`: the source gets `(context, { tree, node })`. An HTML editor also carries the `css` sources (they fire in `<style>` and `style=""`) and the `javascript` ones (`<script>`, `on…=""`); its `html` sources stay out of those regions. A CSS, JS or JSON editor carries only its own.

## How are options ranked?
- `PgCodeEditor.addCompletionRanker(language, function (option, context, { tree, node, language }) { return boost; })`: the number is added to the option's `boost` (the sum kept in -99..99) for every option offered where that language is spoken, the engine's own (CSS properties, tags) included. A moved option is copied; a ranker that throws counts 0. Editors already open use it from their next completion.

## How does a colour show in the list?
- An option with `swatch: '<css colour>'` (a design token's value) gets a square `.pg-ce-swatch` before its label; so does a label that is `#hex`, `rgb()`/`hsl()`/`oklch()`… or, with type `constant`, a named colour (`CSS.supports('color', …)`; the engine's CSS colour names are constants).

## Why does the engine ask its sources through slots?
- `autocompletion({ override })` holds 16 slot sources; slot i runs the i-th source of `state.languageDataAt('autocomplete', pos)` (same sources, same order as without override), then the rankers. Override is the only way to rank the engine's own options.
- Each slot first calls `syntaxTree(state).resolveInner(0, 0)`. `resolveInner()` starts from the node it found last (a per-tree cache in @lezer/common 1.5.3); after a lookup at a `style=""`/`on…=""` value it stays on the `AttributeValue` and never enters the CSS or JS mounted over it, so the CSS source offered nothing there. A `<style>` block is not affected.

## How is it tested?
- `tests/code_editor_completion_test.php` runs the facade's language and completion blocks under Node on the bundle (a fake `document.body.style` gives the engine's CSS property list). The list and swatch drawing need a real view: checked once in Chromium.
