---
name: designer-sketch-drawing-picture
description: The reference picture as drawn (ai-and-picture.html, boards E4 and E5): the picture under a sketch page, its rows in "More", and "Read the picture" with its proposal, "Keep" and "Discard"
---

## How does a reference picture lie under a sketch page?
- "Reference picture" in "More" opens today's picture chooser. A file that is not a picture is refused by the chooser as today.
- The picture fills the frame's width from the top, at about a third of its strength; the frame grows as tall as the picture.
- It takes no pointer: boxes are drawn over it as on an empty page.
- It is only for drawing: kept with the sketch, not built into the page, and sent nowhere: "Read the picture" reads it in the browser.
- A design started from Canva pages opens with each page's picture in this place (`designer-start-import-drawing-canva.md`).

## Which rows does "More" get with a picture there?
- The one row "Reference picture" becomes three under the caption "Reference picture": "Hide the picture" (then "Show the picture"), "Change the picture", "Remove the picture".
- "Remove the picture" asks nothing; Ctrl+Z brings it back.
- "Read the picture" is a fourth row under the caption "Reference picture", shown only where the browser's built-in model takes images (`PinegrapAiWrite.imageReady()`); the sheet draws it under "Pinegrap AI", which the owner's decision replaced (`workflow/decisions-made-product-111.md`).

## How does "Read the picture" run?
- A person's first use shows one line of what is sent, in the place of the foot under the frame: "“Read the picture” reads this picture with your browser's built-in AI. It is sent nowhere." (the sheet still says it is sent to Pinegrap AI) with "Cancel" (quiet, small) and "Read the picture" (filled, small). It is remembered per person, as the other first-use lines are; later uses start at once.
- Busy: the head shows a spinner, "Reading the picture…" and "Stop" (quiet, small).
- The proposal is drawn in the accent, without chips, over the picture. A foot under the frame counts it: "9 boxes proposed from the picture", with "Discard" (quiet, small) and "Keep" (filled, small).
- "Keep" makes them boxes, read like any other. "Discard" or Escape takes them away.
- On a page that already has boxes the foot says so in one line: "9 boxes proposed. “Keep” replaces the 3 on the page; one Undo brings them back."

## What are its messages?
- Nothing found: "No boxes could be read from this picture."
- No answer: "The picture could not be read. Nothing was changed." with "Try again" (quiet, small).
