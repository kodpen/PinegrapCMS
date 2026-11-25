---
name: designer-options-layers-drawing-layers
description: Background layers as drawn for DO13 (options-layers.html, boards L1 to L4): the list, the three kinds and their rows, a gradient's strip and stops, palette colours, order, states
---

## How is the list drawn?
- In the Style tab's "Color and background", a sub-head "Background layers" after "Background Color": one line a layer, then a foot with the count ("3 layers · the first is in front") and "Add layer", which lists Colour, Gradient, Picture. With none: "No layer. The background colour above shows."
- The first line is in front, as the Layers tab orders elements. "Background Color" is the theme colour under every layer.
- A press on a line opens its rows under it; one layer is open at a time, and the open line shows the bin.
- An element with one background made before (a custom colour, a two-colour gradient, a picture) reads as one layer and is written back as it was until a layer is changed, added, removed or moved.
- Eight layers is the most: the foot reads "8 layers, the most an element takes" and "Add layer" is off.

## What does a gradient layer hold?
- "Type": Linear with "Angle", Radial with "Shape" (Circle, Ellipse) and "Center", Conic with "Start" and "Center". "Center" is the nine places Transform's Origin uses. Changing the type keeps the stops.
- The strip shows the stops in a row whatever the type, on a checked ground; under it a handle a stop, dragged to move it, the chosen one ringed. A press on a free place adds a stop of the colour found there ("Press the strip to add a stop.").
- The chosen stop: a line "Stop 2 of 3" with the bin, then "Color", "Opacity" and "Position". Two stops is the least (the bin is off), sixteen the most.
- "Color" lists the design's colours (Primary, Secondary, Success, Danger, Light, Dark, Body background), then "Custom…". A palette colour is written as the theme's variable and carries a link mark: a palette change follows.

## What do a picture and a colour layer hold?
- Picture: "Picture" (a small picture, the file's name, the folder button that opens the file picker the "Bg Image" row opens today; tip "Choose a picture"), "Size" (Cover, Contain, Original, Custom with a width and a height), "Position" (the nine places). Under "More": "Repeat" (No repeat, Repeat, Across, Down), "Attach" (Scroll, Fixed), "Blend" (the layer's blend with the layers under it). A gradient layer has the same "More".
- Colour: "Color" from the palette or custom, and "Opacity".
- A picture no longer in the files: the line's summary ends "not found", a warn line "This picture is no longer in the files. The layer draws nothing until it is replaced."

## What of a background the list cannot read?
- A background written by hand (the Code tab) stays as it is: "This background is written by hand and is kept as it is." with "Start a list", which asks once "Replace this background with a list of layers?" and begins empty.

## How is the order changed, and what of states?
- A line is dragged by its grip, a blue line showing where it lands; with the keys, Alt with Up or Down, and one line says it ("“Picture” is now layer 2 of 3."). Each move is one undo step.
- The layers are the same in every state: under Hover, Focus or Pressed the list is dimmed in place with "The layers are the same in every state."; "Background Color" is the row a state changes.
- At 240 px the summary is cut with "…"; the kind and the small picture always show.

Related: docs/memory/designer/designer-options-layers-drawing.md, docs/memory/designer/designer-options-states.md
