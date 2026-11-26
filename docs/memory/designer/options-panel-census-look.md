---
name: options-panel-census-look
description: Options panel census, part 3: gaps to dress and move an element (hover/focus/pressed, transitions, gradients, filters, blend, transforms, text effects, masks)
---

## Hover, focus and pressed states?
- No Options row is written for a state. Interactions' hover trigger plays one effect (fade, lift, scale); click toggles `pg-fx-on`; Focus Ring is one switch. A hover colour, a focus outline or a pressed shadow is code in "This element" (`.pgs-x:hover { … }`).
- Missing: a state chooser in the Options panel head (Normal, Hover, Focus, Pressed) under which Color and background, Border and corner, Shadow and effect and transforms write `:hover`, `:focus-visible`, `:active` rules on the element's class; the canvas previews a state on the selected element. M. Reads the theme colours (`--bs-*`, `--pg-primary-fg`), `--pg-shadow*`, `--pg-btn-hover-transform`, `--pg-card-hover-transform`, `--pg-card-shadow-hover`.

## Transitions and entrance motion?
- A transition between states is code. Missing: Transition (property group, duration, easing) on the element. S. Reads `--pg-duration` and `--pg-ease`.
- Entrance motion has ten effects (Fade in, four slides, Scale, and Zoom out, Blur in, Rotate in, Reveal since DO18: `designer-interactions-more-entrances.md`) with Duration, Delay, Easing, Distance, Stagger. Missing: a choice of easing curves beyond the stored list. S. Reads `--pg-duration`, `--pg-ease`. Endless motion (pulse, float) is not offered; `designer-block-motion.md` binds what blocks may do.

## Gradients and several backgrounds?
- Custom Background Color gives one gradient of two colours with a middle, and Bg Image one picture. A picture over a gradient, a gradient overlay on a photo, three or more stops, conic gradients are code. Missing: a list of background layers (colour, gradient, picture) in order, each with its own size, position and repeat. M. Reads the theme colours as stop choices.
- The gradient colours are typed hex values, not theme colours. Missing: stops chosen from the palette (`var(--bs-primary)`), so a palette change follows. S. Reads `--bs-*` theme colours.

## Filters and blend?
- Built by DO15 (#11700): Filter's seven sliders, Background blur and both Blend modes under Shadow and effect's "More" (`designer-options-effects.md`). Opacity keeps its five steps.

## Transforms?
- Only through Interactions' start states. Missing: Rotate, Scale, Move X and Y, Skew and Origin on the element, combined with Interactions' own transform (`pgFxBase` keeps the node's). M (composition with pg_fx and per state). Reads none.

## Text effects?
- Built by DO16 (#11720): Text shadow, Letter spacing (a heading starts from `--pg-heading-tracking`), Font family from `--pg-font-body` / `--pg-font-heading`, "Limit to lines", Gradient fill from theme colours and Outline under the Text section's "More" (`designer-options-text.md`). Still missing: Text wrap balance for headings (S, none).

## Masks and clip shapes?
- Missing: Clip shape (circle, ellipse, inset with corners, a few polygons such as slant and arrow) and Mask (a fade to transparent at one edge, a picture as mask) on any element. M (shape editor and canvas preview). Reads `--pg-radius-*` for inset corners.

## Cursor and pointer?
- Built by DO17: Cursor (Pointer, Default, Not allowed) and "Let clicks through" (`pe-none`) under Shadow and effect's "More", Normal only (`designer-options-pointer.md`).
