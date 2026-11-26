---
name: designer-options-page-transition
description: "Page transition" (Settings, Theme) and "Keep across pages" (Interactions) as built in DO20: the page entry in the variables file, the rules, the page-only save, the names
module: design_themes
---

## Where are the two settings?
- "Page transition": Settings, Theme, tab "Look and colours", a section under "Colour mode" (`[data-sd-pagetrans]`, drawn by the part `pagetrans`): None, Fade, Slide; then From (The right, The left, Below) for Slide, Duration (slider and number, 0 to 1,000 ms) and Easing (`_sdEaseHtml()` / `_sdEaseWire()`, curve button and all), a figure of two pages and "Play". None is the start.
- "Keep across pages": the Interactions card, sub-head "Between pages" after the effect's rows (`_sdKeepHost()` hands the part the element; drawn for an element that may have its own custom style). On: a "Name" list with the design's names and their pages, then "A new name…".

## How is the transition kept?
- As the model's `page` entry in the design's variables file (`design-variables-tab.md`): `{kind, from?, duration?, easing?}`, made safe by `pg_design_page_transition_clean()` (twin `PGPageTrans.clean()`). None keeps no entry, so a design never given one writes its file byte for byte as before. No column.
- Duration and Easing left out follow the look: `var(--pg-duration, 0.25s)` and `var(--pg-ease, ease)`.
- The editor reads the saved choice from the file's head (`PGPageTrans.fromCss()`, one fetch of `themes.variables`).

## How does a change reach the save?
- Through the hidden input `style_variables`: into the Variables tab's model when it holds one, else as `{"page_only":1,"page":…}`. `pg_design_variables_save()` sets a page-only post on the stored model (`pg_design_variables_with_page()`), so the stored values stay; no file and None writes nothing.
- The input's readers skip a page-only post (`_sdVarsInputModel()`), and the tab's `setModel()` carries its page into the model it sets.

## What is written?
- `pg_design_page_transition_css()`, appended to the variables file: inside `@media (prefers-reduced-motion: no-preference)`, `@view-transition { navigation: auto; }`, the duration and easing on `::view-transition-group/old/new(*)` (old and new do not inherit the easing), and for Slide two keyframes on `::view-transition-old/new(root)`.
- A browser without view transitions ignores it; a visitor who asks for less motion gets none. Both pages need it, so it plays only between pages of the same design. The canvas never plays it.

## How is an element kept?
- `view-transition-name: <name>` in the element's own custom style body (`PGPageTrans.withName()`, written by `_sdWriteCustomStyle()`: one undo step, the class added the first time). Names: small letters, digits, hyphens, at most 40 (`pg_design_view_transition_name_clean()`).
- Switched on, the name is made from the kind and the nearest section's id ("hero-picture"), -2, -3 while the open page has it.
- Warn line when two elements of the open page hold the name; info line while "Page transition" is None; an element in a loop area is dimmed with its line.

## What is not built?
- The link mark and name on a kept element's selection tag (board P3).
- The figure plays a Slide or Fade; the Preview shows the transition only from the saved file.

## Where is it tested?
- `tests/design_page_transition_test.php`: clean, CSS, the file round trip, the page-only merge, names, and the script twins under Node.

Related: docs/memory/designer/designer-options-curve-drawing-pages.md, docs/memory/designer/design-variables-tab.md, docs/memory/designer/designer-options-curve.md
