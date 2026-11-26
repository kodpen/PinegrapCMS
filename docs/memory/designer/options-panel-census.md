---
name: options-panel-census
description: Start file of the Options panel census (DO1, 2026-11-24) and what was built from it (DO2 to DO26, done 2026-11-26): which file holds each row or component now
---

## What was this census?
- Step DO1 (#11490): a count, no product code, of the Page Design Studio's Options panel (tabs Content, Style, Advanced) as `style_designer.src.js` built it on 2026-11-24, against what a designer could reach then only through code ("This element" card, User Styles, Attributes, a typed class).
- Owner (2026-11-24, 2026-11-25): more options of good quality; every package shown goes in (`workflow/decisions-made-product-105.md`, `-110.md`). Built as DO2 to DO26, closed 2026-11-26. How a new row enters the panel and what it keeps: `designer-options-new-row.md`.

## Which file holds what?
- The panel on 2026-11-24, per element kind and section: `options-panel-census-today.md`.
- Placing and sizing (grid, position, per size, sticky, snap, ratio, fit): `options-panel-census-layout.md`.
- Dressing and moving (states, transition, layers, effects, transform, text, shapes, cursor): `options-panel-census-look.md`.
- Bootstrap Studio's list as have, partly or missing: `options-panel-census-bss.md`.

## Where does each built step live?
- A value per screen size, DO2: `designer-size-per-screen.md`. Hide on and Sticky from, DO3: `designer-hide-on-sticky-from.md`.
- Grid Columns and Rows, DO4: `designer-grid-columns-rows.md`; a child's span and start, DO5: `designer-grid-child-place.md`; named areas, DO6: `designer-grid-areas.md`.
- Position presets and Z Index, DO7: `designer-position-presets.md`. Scroll snap, DO8: `designer-scroll-snap.md`. Aspect ratio and Object Fit, DO9: `designer-aspect-ratio-object-fit.md`.
- State row, DO10: `designer-options-states.md`. Transition, DO11: `designer-options-transition.md`. Transform, DO12: `designer-options-transform.md`.
- Background layers, DO13: `designer-options-bg-layers.md`. Clip shape and Mask, DO14: `designer-options-shape.md`.
- Filter, Background blur, Blend, DO15: `designer-options-effects.md`. Text effects, DO16: `designer-options-text.md`. Cursor, DO17: `designer-options-pointer.md`.
- Four more entrances, DO18: `designer-interactions-more-entrances.md`. Easing curve, DO19: `designer-options-curve.md`. Page transition, DO20: `designer-options-page-transition.md`.
- Back to top and Scroll progress, DO21: `page-scroll-components.md`. A menu's scrollspy and scrolled state, DO22: `menu-scroll-rows.md`.
- New collection with pages, DO23: `collection-pages.md`. Favourites, DO24: `palette-favourites.md`. Find and Replace scopes, DO25: `designer-find-replace-scopes.md`. Markdown, DO26: `markdown-component.md`.
- The sheets drawn first: `designer-options-states-drawing.md`, `designer-options-grid-drawing.md`, `designer-options-layers-drawing.md`, `designer-options-curve-drawing.md`, `designer-options-collection-drawing.md`.

## What did S and M mean?
- S: one row or a few values writing a Bootstrap class or a prop the page already prints; a few hundred lines.
- M: a new kind of stored value (a state, a per size length, a list of layers) with its own writer, canvas preview, way back from the code view and tests; about 1,000 lines.

## What are "the design's variables"?
- The look's `--pg-*` tokens (radius, shadow, border width, `--pg-duration`, `--pg-ease`, the two font families, line height, button and card hover) and the `--bs-*` variables of the palette and Themes panel (theme colours, radius, shadow, `--bs-breakpoint-*`): `design-theme-look-and-palette.md`. A row offers them first, so a changed look carries.

## What is still left out?
- Text wrap balance for headings; one "Check for issues" window; a designer's own options on a component (`options-panel-census-bss.md`). None has a step.
