---
name: designer-style-guide-sheet
description: The Style guide sheet of the Preview menu (SG2, #11656): the part 'styleguide', its window and frame, forced control states, components drawn as scrolled to, the style_guide read
module: design_style_guide
---

## Where is it opened?
- "Style guide" is the last row of the Preview menu (`#sd-vb-styleguide`, under a line) and a row of the command palette (`act_style_guide`). No bar button, no key.
- Designers only: `_sdStyleGuideReady()` (full access, a Bootstrap design, the part handed); the part is `designer` and `designer/style_guide` is not in the content roles' list. Opening it to content roles would widen a right and is asked first.
- The core keeps `_sdStyleGuideOpen()` and `_sdStyleGuideHost()`; the part `styleguide` (`assets/js/style_designer_styleguide.js`) loads on the first press. Kit key added: `prefetchShared`.

## What does the server answer?
- `designer/style_guide` (`includes/panel/designer.php`, a clean sub-action): look, palette, the Variables tab's unsaved model or `style_id` for the stored file, and `css`, the canvas's own stylesheet text (inline styles and readable linked rules, not Bootstrap, theme or editor sheets; `PG_STYLE_GUIDE_CSS_MAX`, 256 KB).
- It answers `pg_style_guide_model()` (`design-style-guide-model.md`) with each component's `pages`: `pg_style_guide_component_pages()` counts the pages placing it, once each, the recycle bin left out.

## How is the sheet drawn?
- A `<dialog>` 1040 px wide at most, 24 px from the edges; under 768 px the whole screen and the design's name left out. Head: "Style guide", the name, "Light | Dark", close. A strip of the six block names: one Tab stop, arrows, Home and End; the block on screen is marked, a pressed name stays marked while the sheet scrolls to it.
- Grey lines until the frame is ready. The frame copies the canvas's `<base>` and every stylesheet but the editor's own (`sd-*`, `sd-icons-css` kept for the icons): samples are the real thing, unsaved changes included. The sheet's own layout uses `pgsg-` classes and `role="heading"` divs, never `h2`, so the design's heading rules leave it alone.
- Shown once every linked stylesheet has loaded (4 s at most). Then every `:hover`, `:focus` and `:focus-visible` rule the frame can read is copied with the state as `.pgsg-s-hover` / `.pgsg-s-focus` (`#pgsg-states`): a missing focus ring shows as missing.
- Samples are `inert`, `tabindex="-1"`: no click, no Tab stop.

## What do the blocks show?
- Colours: card per colour (light left, dark right; one value without a dark mode), "Your own" tag; the pairs of the mode shown with "Passes", "Large text only", "Too weak for text" and an icon.
- Type: the two fonts, a row per style drawn by its own selector (`h1`, `.display-1`, `p`, `small`, `.own-class`), cut with an ellipsis; size, px, weight, and "changed in the stylesheet" / "your own class"; the scale's "Aa" on one base line. Spacing: bars at three times their px, own sizes in the accent colour of the mode shown. Shape: corners, line width, the `.shadow` class with the option's label.
- Controls: each in its model states with the state's name under it; two by two under 720 px of sheet.
- Components: two cards a row, "on n pages" or "not placed"; drawn with `_sdEmitNode()` of a `shared_ref` when within a screen of the view (an IntersectionObserver in the frame, `prefetchShared()` for trees not loaded); cut at 360 px with "Show all of it"; an empty draw says it could not be drawn.

## Dark and no variables?
- The switch shows when the design's colour mode is dark or auto, or the canvas shows dark; it opens on the canvas's mode and sets only the frame's `data-bs-theme`, redrawing the pairs and the spacing bars.
- Without variables a line under the strip offers "Answer the questions": the sheet closes, Settings opens on Theme, Variables, and the start questions are asked.

## Print and names?
- SG3 (#PRNUM): `designer-style-guide-print-links.md`.
