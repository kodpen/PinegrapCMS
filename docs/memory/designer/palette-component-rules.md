---
name: palette-component-rules
description: Rules for a new designer palette component: the full guide, no script or stylesheet, render equals toHTML, tree by default, inline style, cssClass vs _attrs, grid, canDrop, accessibility, registration
---

## Where is the full guide?
- `docs/component-development-guide.md`, in the repository (Turkish): node model, canvas, serialization, auto-wrap, and the section 9 checklist a component must pass before it counts as done. Read it end to end before writing a palette component; this file is its summary.
- Parts of the guide are older than the code: it names Bootstrap Icons 1.11.3 (designs load 1.13.1, `pg_design_icons_url()`) and shows monolithic `render()`/`toHTML()` components such as pricing and hero, which explode into trees now (`palette-component-atomic.md`).

## What may a component emit?
- No custom JS: no `<script>`, `onclick`, `onload`; behaviour only through Bootstrap `data-bs-*` attributes.
- No new `.css` file, `<link>` or `<style>`; only Bootstrap 5.3.8 and Bootstrap Icons. Where block CSS may live: `designer-block-style.md`.
- `render(doc, p)` and `toHTML(p, pad)` produce exactly the same markup; a mismatch is the most common bug.
- Default is a tree of `semantic` nodes (explosion). A `COMPONENTS` entry with `render()`/`toHTML()` only for a single element (`btn`, `badge`) or output drawn per request (`language_switcher`).

## How are classes, styles and attributes written?
- No fixed inline style: find the Bootstrap utility. Inline style only when the value comes from a user prop and no utility covers it, written through `esc()`.
- Classes go in the `cssClass` prop; `_attrs` holds `data-*`, `aria-*`, `role`, the user's `style` and custom attributes. Props starting with `_` are designer metadata and never reach `style_code`; a new metadata flag takes the `_` prefix.
- Layer names: `designer-layer-names.md`; sample content: `designer-block-sample-content.md`; every text through `_sdT()`: `designer-sdt-translation.md`.

## What structure must a tree keep?
- Grid: `container`/`container-fluid` > `row` > `col`; breakpoints mobile first (`col-12 col-md-6 col-lg-4`). Utility order in a class list: display, flex/grid, spacing, sizing, typography, colour, border, shadow, the rest.
- `canDrop()` semantics: a void tag takes no children; nothing interactive inside `<a>` or `<button>`, at any depth; `<p>`, `<button>` and `<h1>`-`<h6>` take inline content only (a heading and `<p>` also take a link or button); a `<label>` holds one control; a palette item is judged by the tree it builds (inline-drop-links-buttons.md); `<ul>`/`<ol>` take only `<li>` (a loop area excepted); `<table>` > thead/tbody/tfoot/caption/colgroup > `tr` > `td`/`th`; no form inside a form or a custom form widget, at any depth, a form inside a dragged block too (`_sdDragCarriesForm()`, `_sdInForm()`).
- Accessibility minimum: `aria-label` on an icon-only button or link, `aria-expanded` + `aria-controls` on a toggle, `alt` on `<img>` (`alt=""` when decorative), `<label for>` + `id` on an input, `aria-hidden="true"` on a `bi` icon beside a label.
- Row gutter: `gutter: '4'` plus `cssClass: 'g-lg-5'`, never plain `'5'`: the page scrolls sideways on phones. Measure a block's responsive behaviour alone, off the canvas, in a clean Bootstrap page (`designer-gutter-overflow.md`).

## Where does a new item get registered?
- A ready section: `_sdBlockPaletteItems()` and its prefix in `_sdBlockPaletteGroups()` (`designer-palette-tabs-and-blocks.md`).
- A Components tab item also gets an entry in `_sdPalInfo()`, keyed by `_sdPalKey()` (`c:<componentType>`, `t:<contentType>`, `s:<tag>` or `s:input:<type>`, `f:<cfType>`, `r:<regionType>`, a grid item by its type such as `container`): `d` one-line description (`_sdT()`), `k` English/HTML search words, `h` the markup it writes, `v` a drawing for `_sdPalDrawing()` (grid and Bootstrap items). Without an entry the item opens no hover card and is found only by its label, the English it was translated from and its type or tag.
