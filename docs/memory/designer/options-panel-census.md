---
name: options-panel-census
description: Start file of the Options panel census (DO1, 2026-11-24): what the designer's Options panel sets today and what needs code, each gap sized S or M
---

## What is this census?
- Step DO1 of `plans/designer-options-census.md`: a count, no product code. It lists the controls of the Page Design Studio's Options panel (tabs Content, Style, Advanced) as `style_designer.src.js` builds them on 2026-11-24, and against them what a designer can reach today only through code: the Styles panel's "This element" card (`custom-style.md`), User Styles, the Attributes panel or a class typed by hand.
- It names no control as dead or unused. The owner picks from the gaps; the architect then writes DO2 onward.

## Which file holds what?
- Controls today, per element kind and per section: `options-panel-census-today.md`.
- Missing ways to place and size an element (grid tracks and areas, position and layer order, per screen size values, sticky and scroll snapping, aspect ratio, object fit): `options-panel-census-layout.md`.
- Missing ways to dress and move it (hover, focus and pressed states, transitions and entrance motion, gradients and several backgrounds, filters and blend, transforms, text effects, masks and clip shapes): `options-panel-census-look.md`.

## What do S and M mean here?
- S: one new row or a few values in a row that writes a Bootstrap class or a prop the page already prints, with the twins (canvas, editor HTML, site) already in place; one PR of a few hundred lines.
- M: a new kind of stored value (a state, a per-size length, a list of layers) that needs its own writer to User Styles or the node's style, its canvas preview, the code view's way back and tests; one plan step of about 1,000 lines.

## What are "the design's variables"?
- The look's `--pg-*` tokens (`--pg-radius-*`, `--pg-shadow*`, `--pg-border-width`, `--pg-duration`, `--pg-ease`, `--pg-font-body`, `--pg-font-heading`, `--pg-line-height`, the button and card hover tokens) and the palette's and Themes panel's `--bs-*` variables (theme colours, `--bs-border-radius*`, `--bs-box-shadow*`, `--bs-breakpoint-*`, custom variables): `design-theme-look-and-palette.md`, `designer-theme-panel-light-selector.md`.
- A gap marked "reads" offers those variables as its choices or defaults, so a design that changes its look changes the value with it; "none" means it takes plain values.

## Which gaps were found, in short?
- Layout file: grid tracks (M), grid areas and spans (M), position offsets per size (M), layer order typed or from tokens (S), width and height per size (M), sticky top per size (S), scroll snapping (S), aspect ratio on any element and per size (S), object fit on video and per size (S), object position as presets (S).
- Look file: hover, focus and pressed states (M), transitions (S), entrance motion on more effects and easings (S), several backgrounds and gradient stops (M), gradient from theme colours (S), filters (S), backdrop blur (S), blend modes (S), transforms (M), text shadow, letter spacing and font family (S), text gradient and stroke (S), masks and clip shapes (M), cursor and pointer (S).
