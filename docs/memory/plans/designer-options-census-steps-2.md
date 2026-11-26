---
name: designer-options-census-steps-2
description: Plan designer-options-census, DO10 to DO20: states, transition, transform, background layers, clip shape and mask, the small effects, new entrances, the easing curve, page transitions
priority: 7
---

## What holds for these steps?
- The frame of `designer-options-census.md`. The gaps as counted: `designer/options-panel-census-look.md`.
- DO10 to DO12 are built as drawn in `designer/designer-options-states-drawing.md` (the State row, Transition, Transform, a section's "More"); the drawing wins where it differs from the lines below.

## Which steps add states and shapes? (line "states and shapes")
- DO10 (M) after DD22, done (#11588): States. A "State" row, first in the Style tab's body, never in the panel's head: Normal (the default), Hover, Focus, Pressed.
  - Under a state, Color and background, Border and corner and Shadow and effect write `:hover`, `:focus-visible` and `:active` rules on the element's class in User Styles; the other sections are dimmed with one line saying why.
  - The canvas shows the chosen state on the selected element only; choosing another element goes back to Normal. A section holding a state's value shows a dot; "Reset this state" clears the state.
- DO11 (S) done #11634: "Transition" in Shadow and effect: what changes (Colours, Shadow, Size and place, All), Duration and Easing, starting from `--pg-duration` and `--pg-ease`; nothing moves under reduced motion.
- DO12 (M) done #11671: "Transform": Rotate, Scale, Move X and Y, Skew and Origin, in every state; composed with an Interactions effect's own transform (`pgFxBase` keeps the node's), never overwriting it.
- DO13 (M) done #11737: background layers: a list of layers (colour, gradient, picture) in order, each with its own rows; a gradient linear, radial or conic with three or more stops, a stop chosen from the palette so a palette change follows.
  - An element with one background reads as one layer and is written back unchanged until it is changed.
- DO14 (M) done #11786: "Clip shape" (Circle, Ellipse, Rounded inset, Slant, Arrow) and "Mask" (a fade at one edge, a picture as the mask), shown on the canvas as chosen. (`designer/designer-options-shape.md`)

## Which steps add the small effects? (line "small rows", after DO9)
- DO15 (S) done #11700: Shadow and effect, under "More": Filter (Blur, Brightness, Contrast, Grayscale, Saturate, Sepia, Hue) as sliders, "Background blur" for a glass panel, "Blend" for the element and for its background.
- DO16 (S) done #11720: Text, under "More": Text shadow, Letter spacing (a heading starts from `--pg-heading-tracking`), Font family from the design's two families, "Limit to n lines", a gradient fill and an outline on text.
- DO17 (S) done #11745: "Cursor" (Pointer, Default, Not allowed) and "Let clicks through" (`pe-none`).

## Which steps add motion? (line "motion")
- DO18 (S) done #11572: Entrance motion: "Zoom out", "Blur in", "Rotate in" and "Reveal" beside the six, with the same Duration, Delay, Easing, Distance and Stagger (`designer/designer-interactions-part.md`), on the site and in the Preview.
- DO19 (M) done #11739: the easing curve drawn and dragged, offered wherever Easing is chosen (Interactions, DO11's Transition); a curve is kept in the stored list under a name.
- DO20 (M) done #11755: page transitions: one "Page transition" for the design (None, Fade, Slide), written as `@view-transition`, and "Keep across pages" on an element (the same `view-transition-name` on both pages).
  - A browser without view transitions, and reduced motion, draw as today.
