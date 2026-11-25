---
name: design-variables-overview
description: A design's variables (colour, type, spacing, shape): what the feature is, the rules that bind it, the Variables tab, the Options chip, start questions, Export and Import, which file holds what
module: design_variables
---

## What is it?
- A design's named values in four groups (Colour, Type, Spacing, Shape) plus "Your own", edited in Settings, Theme, "Variables", offered by the colour, size, spacing and corner controls of Options, asked by five start questions and carried in and out as a Design Tokens file. Landed in 2026.11.0 (DV1 to DV5, #11495, #11553, #11589, #11622, #11593, #11660).
- Owner (2026-11-24): the designer gets design variables before the launch; the start asks them and the page drawn in the Prototype is filled with them, because they add value to the designer (`workflow/decisions-made-product-105.md`).

## What binds every change?
- No table or column: the variables are one more design CSS file with the `/*! pg-theme {json} */` head, loaded after the palette. A change that seems to need a column stops and asks the architect.
- A design that never saved variables has no file, and its published page does not change by one byte; a file of untouched defaults changes nothing a page reads.
- A bound control holds an inline `var(--pg-var-…)` expression, never a copied value, so a later change of the variable moves every bound element.
- Starter blocks and palette components use variables, never literals; how far they are today: `design-variables-starters.md`.

## Which file holds what?
- The model, the list and limits, defaults, the stylesheet and its tokens, the contrast check: `design-variables-model.md`.
- The Variables tab, where the file is kept, the save, canvas, Preview and site loading: `design-variables-tab.md`.
- Options controls offering variables, the chip and "Detach", "Used in n places": `design-variables-options.md`; the spacing box and corners: `design-variables-spacing.md`.
- The five start questions and "Ask me again": `design-variables-questions.md`.
- "Export" and "Import" (Design Tokens format, the refusals): `design-variables-files.md`.
- The drawings the screens follow: `designer-variables-drawing.md`, `designer-variables-drawing-questions.md`, `designer-variables-drawing-computed-rows.md`.
- Others that read the model: the Figma import (`figma-start-import.md`), the Canva start's colours (`canva-start.md`), the style guide sheet (`design-style-guide-model.md`), the sketch builder's section rhythm (`sketch-builder.md`).

## How is a new variable or control added?
- A variable: a row in `pg_design_variable_list()` with its kind and checks; it writes its `--pg-var-<key>` token (underscores become hyphens) and, only when it differs from the default, the theme tokens it drives (`design-variables-model.md`). Add its Design Tokens name to the import's map (`design-variables-files.md`).
- A control: wrap its Options row with `vars` (`design-variables-options.md`); a tested pure helper under Node.

## What is not done yet?
- Duplicating a design does not copy the variables file (`design-variables-tab.md`).
- Scale, base unit and section rhythm drive no theme token, so only bound nodes read them.
