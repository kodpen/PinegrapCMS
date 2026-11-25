---
name: designer-options-census
description: Plan (owner 2026-11-24, 2026-11-25): the eleven packages he picked from the Options panel census as DO2 to DO27 in three steps files; how an option enters the panel, the lines, what a step keeps
priority: 7
---

## What is asked?
- Owner (2026-11-24): "daha fazla özelleştirme seçenekleri kaliteli şekilde planlayalım" (`workflow/decisions-made-product-105.md`). DO1 counted what needs code today (`designer/options-panel-census.md`, #11490).
- Owner (2026-11-25): every package he was shown goes in (`workflow/decisions-made-product-110.md`).

## Where are the steps?
- Placing and sizing, DO2 to DO9: `designer-options-census-steps.md`.
- Dressing and moving, DO10 to DO20: `designer-options-census-steps-2.md`.
- Page components, the collection, finding, DO21 to DO27, and the sheets drawn first: `designer-options-census-steps-3.md`.

## How does a new option enter the panel?
- By `designer/designer-light-and-simple.md`: no new button in the Options panel's head, the page bar or the canvas bar. A new row sits in the section that holds its kind today (Text, Color and background, Border and corner, Size and display, Shadow and effect, Advanced › Layout), under that section's "More" unless its step shows it at once.
- A row copies a row kind the panel has: a list, a slider with its number, chips, a colour, the Spacing box's size chooser. Only a part with no such model has a sheet drawn first, named in its step after "after".
- What the look already carries is offered first: the `--pg-*` tokens and `--bs-*` variables the census names for the gap.
- Each row is found by the Options search (`designer/designer-options-search.md`) under the word a designer would type.

## What must every option keep?
- An element with the option unset draws byte for byte as today on the site, the canvas and the Preview; a page saved before needs no change.
- One writer per value, shared by the site's render and its browser twin; the code view reads the written rule back into the row (`designer/custom-style.md`).
- Written to the element's own class in User Styles, or as a Bootstrap class where one exists; never an inline style, never a rule that reaches another element.
- Several elements selected take a change in one undo step (`designer/designer-options-multiple.md`).
- Code that is more than a row is a designer part loaded on first use (`designer/designer-parts-on-demand.md`), measured by `designer/designer-performance.md`.

## Which lines run side by side?
- Five lines, each one step at a time in its own order: sizes and grid (DO2 to DO6); small rows (DO7 to DO9, then DO15 to DO17); states and shapes (DO10 to DO14); motion (DO18 to DO20); page and site (DO21 to DO26).
- A step that must change a row another line's open PR builds waits for that PR.

## What does every step keep?
- No table, no column, no new right: a step that finds it needs one stops and asks the architect.
- S under 400, M about 1,000 product lines; the pure writer's tests red first; `node --check` on the designer sources; every label through `php tools/tr_add.php`.
- The `[NEW]` line, named by DO27: "Added more ways to shape an element in the Page Design Studio's Options panel".
- Claim as `[plan:designer-options-census] <step> — <topic>`.
