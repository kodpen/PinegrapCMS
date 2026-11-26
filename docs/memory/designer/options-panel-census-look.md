---
name: options-panel-census-look
description: Options panel census, part 3, as built: states, transition, easing curve, background layers, filters, transform, text effects, clip shape and mask, cursor
---

## Hover, focus and pressed states?
- The "State" row, first in the Style tab's body, never in the panel's head: Normal, Hover, Focus, Pressed (DO10, `designer-options-states.md`). Color and background, Border and corner, Shadow and effect and Transform write `:hover`, `:focus-visible` and `:active` rules on the element's class; the canvas shows the state on the selected element only.
- Not built: states for several elements at once, and Custom Background Color under a state.

## Transitions, entrances and easing?
- "Transition" in Shadow and effect: what changes, Duration and Easing, from `--pg-duration` and `--pg-ease`; nothing moves under reduced motion (DO11, `designer-options-transition.md`).
- Entrance motion has ten effects: Fade in, four slides, Scale, and Zoom out, Blur in, Rotate in, Reveal (DO18, `designer-interactions-more-entrances.md`). Endless motion (pulse, float) is not offered; `designer-block-motion.md` binds what blocks may do.
- The easing curve drawn and dragged wherever Easing is chosen, a curve kept under a name as a design variable (DO19, `designer-options-curve.md`).
- "Page transition" for the design and "Keep across pages" on an element (DO20, `designer-options-page-transition.md`).

## Gradients and several backgrounds?
- "Background layers" in Color and background: colour, gradient (linear, radial, conic, two to sixteen stops) and picture layers in order, stops from the palette so a palette change follows, written in the element's `style` where the old background was (DO13, `designer-options-bg-layers.md`). One old background is read as one layer and written back unchanged until changed.

## Filters and blend?
- Filter's seven sliders, Background blur and both Blend modes under Shadow and effect's "More" (DO15, `designer-options-effects.md`). Opacity keeps its five steps.

## Transforms?
- Rotate, Scale, Move X and Y, Skew and Origin in Shadow and effect, in every state, composed with an Interactions effect's own transform (DO12, `designer-options-transform.md`).

## Text effects?
- Text shadow, Letter spacing, Font family from the design's two families, "Limit to lines", Gradient fill and Outline under Text's "More" (DO16, `designer-options-text.md`). Not built: Text wrap balance for headings (no step).

## Masks and clip shapes?
- "Clip shape" (Circle, Ellipse, Rounded inset, Slant, Arrow) and "Mask" (a fade at one edge, a picture) under Shadow and effect's "More", shown on the canvas (DO14, `designer-options-shape.md`).

## Cursor and pointer?
- Cursor (Pointer, Default, Not allowed) and "Let clicks through" (`pe-none`) under Shadow and effect's "More", Normal only (DO17, `designer-options-pointer.md`).
