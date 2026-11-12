---
name: legacy-set
description: A set of classic styles on the move report (LG4): the set key (ordered CSS/JS addresses, collection, theme, layout and sharing settings), who moves alone, the set's dry run
module: legacy_to_designer, legacy_to_designer_plan
---

## What is a set?
- The classic styles that load the same external stylesheets and scripts in the same order, with the same collection, theme, layout override and sharing position: one Legacy design can hold them all (`plans/legacy-group-design.md`, decision `workflow/decisions-made-product-43.md`).
- Inline `<style>` and `<script>` blocks do not count: they become asset rows limited to the pages of the style that had them.

## Who moves alone?
- A style the designer refuses, a style used as a mobile style, and a style no other style shares its files with.
