---
name: designer-options-new-row
description: How a new option enters the Options panel and what every option keeps: its section, the row kinds it copies, unset draws as before, one writer, User Styles or a Bootstrap class
---

## Where does a new row sit?
- No new button in the Options panel's head, the page bar or the canvas bar (`designer-light-and-simple.md`). A row goes in the section that holds its kind (Text, Color and background, Border and corner, Size and display, Shadow and effect, Advanced › Layout), under that section's "More" unless it is shown at once.
- A row copies a row kind the panel has: a list, a slider with its number, chips, a colour, the Spacing box's size chooser. A part with no such model gets a drawing first (`workflow/design-work-session.md`; a sheet is a folder under `designer/` with a light and a dark picture), as the five sheets of the census did.
- What the look carries is offered first: the `--pg-*` tokens and `--bs-*` variables (`options-panel-census.md`), so a changed look carries into the value.
- The Options search finds the row under the word a designer would type (`designer-options-search.md`); a row under a closed "More" is found only once it is open.

## What must every option keep?
- Unset, an element draws byte for byte as before on the site, the canvas and the Preview; a page saved before needs no change. An old form (the ratio box, one background, a hand-written rule) is written back unchanged until the row is changed.
- One writer per value, shared by the site's render and its browser twin, a pure function with tests (red first); the code view reads the written rule back into the row (`custom-style.md`).
- Written to the element's own class in User Styles, or as a Bootstrap class where one exists; never a new inline style, never a rule that reaches another element. Background layers stay in the element's `style` attribute, where Custom Background Color always wrote (`designer-options-bg-layers.md`). A per size value is an `@media (min-width: …)` rule (`designer-size-per-screen.md`); a state's value a `:hover`, `:focus-visible` or `:active` rule (`designer-options-states.md`).
- Several elements selected take a change in one undo step (`designer-options-multiple.md`).
- Motion stops under reduced motion and draws as today in a browser without the feature (`designer-options-transition.md`, `designer-options-page-transition.md`).
- Code that is more than a row is a part loaded on first use (`designer-parts-on-demand.md`), its cost measured (`designer-performance.md`).
- No table, column or right for a row; a row that needs one goes to the architect first.

## Why these rules?
- Owner (2026-11-24): more options, of good quality (`workflow/decisions-made-product-105.md`); the light and simple look of the studio stays. The rules kept DO2 to DO26 from changing a saved page and from adding chrome.

Related: docs/memory/designer/designer-options-overview.md, docs/memory/designer/options-panel-census.md, docs/memory/designer/designer-parts-on-demand.md
