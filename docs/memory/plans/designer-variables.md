---
name: designer-variables
description: Plan (owner 2026-11-24, before the launch): a design's variables (colour, type, spacing, shape) in one pane, asked at the start, offered by every Options control; steps DV1 to DV6
priority: 7
---

## What is asked?
- Owner (2026-11-24): "tasarım değişkenleri içinde planlama yap, tasarımcıya değer katacaktır"; the start asks them and the page drawn in the Prototype is filled with them (`workflow/decisions-made-product-105.md`).

## What is there today?
- A design wears a look and a palette, two stylesheets of `--pg-*` tokens, built-in or a design CSS file `file-<id>` (`designer/design-theme-look-and-palette.md`). A value typed in Options is a literal: changing the brand colour later means every node again.

## What is built?
- Variables of a design, in four groups. Colour: brand, accent, neutral, surface, text, success, warning, danger, each with a light and a dark value. Type: heading font, body font (the fonts the site ships), base size, scale, weights, line height. Spacing: the base unit and the section rhythm. Shape: corners, line width, shadow.
- Kept as one more design CSS file with the `/*! pg-theme {json} */` head, loaded after the palette, so no table or column; a step that finds it needs one stops and asks the architect. A design without variables draws as today.
- "Variables" is a tab of the Theme pane: a group per fold, a row per variable with its swatch or sample, the value field and "Used in n places"; "Add a variable" for a designer's own colour or size. A change redraws the canvas at once and is saved with the design.
- Every colour, size, spacing and corner control of the Options panel lists the variables first; a bound value shows the variable's name as a chip with "Detach". Starter trees and palette components use variables, never literals.
- The start questions (five, each can be skipped): brand colour, a second colour or "choose for me", the feel (which sets the look), the type feel, roomy or compact. Asked by the New design window (`designer-start-import.md`) and again from the tab's "Ask me again".
- "Export" and "Import" of the tab: a JSON file in the Design Tokens community format; the Figma import fills the same model.

## Which steps? (S under 400, M about 1,000 product lines; tests red first)
- DV1 (M) The model, pure: the list, defaults from a look and palette, the stylesheet writer and reader, contrast check of text on surface; tests. Done: PR #11495 (`designer/design-variables-model.md`).
- DV2 (M) after DV1: the Variables tab, canvas, preview and site loading the file. Done: PR #11553 (`designer/design-variables-tab.md`).
- DV3 (M) after DV2: the Options controls offering variables, the chip, "Used in n places". Done for the colour and font size controls with the type scale: PR #11589 (`designer/design-variables-options.md`).
- DV3b (S) after DV3: the spacing box and the corners (part `spacing`) offering "Base unit", "Section rhythm", the units, "Corners" and the designer's own sizes, with the same chip and list.
- DV4 (S) after DV2: the start questions as a part the New design window and the tab call. Done: PR #11593 (`designer/design-variables-questions.md`).
- DV5 (S) after DV2: Export and Import, refused with the reason when the file is not tokens.
- DV6 (S) Close-out: memory in `designer/`, starters and palette components moved to variables counted, this file deleted, the `[NEW]` line named.

## What does every step keep?
- A published page of a design that never opened the tab does not change by one byte.
- Claim as `[plan:designer-variables] <step> — <topic>`.
