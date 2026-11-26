---
name: designer-options-bg-layers
description: Background layers in Color and background as built (DO13, #11737): the layers and their rows, palette stops, what is written, old and hand-written backgrounds, the part
module: output
---

## What does the list replace?
- Style tab, "Color and background": the sub-head "Background layers" after Opacity replaces Custom Background Color, Bg Image, Bg Size, Bg Pos, Bg Repeat and Bg Attach (`designer-options-layers-drawing.md`). Background Color (the theme class) stays, under every layer.
- The old Bg Image row's "Edit in Artifact Canvas" button went: a picture layer has the folder button only, as drawn.

## What does each layer hold?
- One line a layer, the first in front; a press opens its rows (one at a time, with the bin). Eight at most; "Add layer" (Colour, Gradient, Picture) puts the new one first.
- Colour: "Color", "Opacity". Picture: "Picture" (file picker), "Size" (Cover, Contain, Original, Custom), "Position" (nine places; a lone picture its box cuts takes the Focus control, `image-focus-control.md`).
- Gradient: "Type" (Linear "Angle", Radial "Shape" and "Center", Conic "Start" and "Center"), the strip with a handle a stop, the chosen stop's "Color", "Opacity", "Position"; two to sixteen stops. "Repeat", "Attach", "Blend" under "More" (not on a colour).
- "Color" lists Primary, Secondary, Success, Danger, Light, Dark, Body background, then "Custom…". A palette colour is written `var(--bs-primary)`, or `rgba(var(--bs-primary-rgb), .45)` with an opacity, and carries the link mark.

## Where is it written?
- In the element's `style` attribute (`setAttrStyleProp()`): one `background-image` list (a colour layer is `linear-gradient(c, c)`, a picture without a file `none`), and `background-size`, `-position`, `-repeat`, `-attachment`, `-blend-mode` as whole lists only when a layer needs a value other than CSS's own. Each write removes the `background` shorthand and the old inline `background-color`; `props._bgGrad` is set to null.
- A per-layer Blend in the style attribute wins over Shadow and effect's "Background blend" class rule (`designer-options-effects.md`).

## What of a background made before, or by hand?
- `PGBgLayers.read()` reads the style: a custom colour reads as a colour layer under the rest, the old two-colour gradient with its middle (a colour hint, kept until a stop is changed), a picture, a shorthand of one picture or gradient. Nothing is written until a layer is changed, added, removed or moved.
- What it cannot read (a repeating gradient, a fuller shorthand, px stop places) shows "This background is written by hand and is kept as it is." with "Start a list" (asks once, begins empty).

## Order, keys, states?
- A grip dragged (a blue line where it lands), Alt with Up or Down on the list; Enter opens, Delete removes. On the strip: Left/Right choose a stop, with Shift move it 1 %, Enter adds one in the widest gap, a press adds one of the colour found there (`mixAt()`, canvas colours of the palette). Every add, removal and move is one undo step and is read out in a live line.
- Under Hover, Focus or Pressed the list is dimmed in place with "The layers are the same in every state." (part `states` dims `[data-sd-bgl-box]`).
- A slider or a dragged stop shows on the canvas element at once and is written when let go.

## How does it load, and what did it cost?
- Core: `_sdBglHtml()` (the place and the empty list) and `_sdBglBind()`; part `bglayers` (`pg_bg_layers.js`, pure, + `style_designer_bglayers.js`, `when: always`) runs when the element has a background or "Add layer" is pressed.
- Core 3,110,452 → 3,094,157 bytes (817,214 → 813,464 gzip): the old popup and its handlers went. Part 29.7 KB (10.5 KB gzip). Unminified (no terser here).

## Where is it tested?
- `tests/designer_bg_layers_test.php` (the model, Node). The panel was driven once in a sandbox browser; not unit tested.

Related: docs/memory/designer/designer-options-layers-drawing-layers.md, docs/memory/designer/designer-options-layers-drawing.md, docs/memory/designer/designer-parts-on-demand.md
