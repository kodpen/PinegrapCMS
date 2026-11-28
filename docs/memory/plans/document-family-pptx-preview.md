---
name: document-family-pptx-preview
description: Plan document-family, in words: the .pptx preview with the tabs Slides and Text, the strip of thumbs, the slide, the line on what was left out, its buttons and limits
---

## How is the preview put together?
- Two tabs in the look's segmented control at the top of the preview's body: "Slides", which opens first, and "Text", today's list unchanged.
- Slides: on the left a strip 148 px wide of numbered thumbs in the slide's own ratio, the chosen one with the look's selected edge, scrolling on its own. Right of it the chosen slide, fitted to the space on the look's ground and never drawn larger than its own size. Under it a quiet line "3 / 24" with Previous and Next.
- Below 768 px the strip lies under the slide as one row that scrolls sideways.
- Keys, while the focus is in the strip or on the slide: the arrow keys change the slide, Home and End go to the first and the last.
- A hidden slide's thumb is dimmed and marked "Hidden slide".
- Speaker notes sit folded under the slide; "Speaker notes" opens them. They come from the text reader, as the Text tab's.
- Thumbs and slides are drawn as they come into view. A slide not drawn yet shows the look's skeleton in its ratio; one that fails shows "This slide could not be drawn." and the Text tab still reads it.

## What does it say about what it left out?
- One quiet line over the slide when the mapper's report counts something: "Shown approximately: 2 charts and 1 table are not drawn, 3 fonts were replaced.". No line when nothing was left out.

## Which buttons?
- In the preview's head beside Download: "Present" and, for whoever may edit, "Edit in Artifact Canvas" (both DF3). No other new control.

## What does it keep?
- The limits of today: 500 slides, the picture and package limits; past one, "Only the beginning is shown.".
- Every text set as text; a picture only through the preview's own picture reads.
- The file window, Quick Look and a channel's Preview draw the same, through `pgOfficeView.render()` kind `pptx`.

## What does DF2 check?
- Words at 4.5:1 in both themes; a visible focus on thumbs; a screen reader hears "Slide 3 of 24" and the slide's title.
- No new component: a piece Design examples lacks is added there in the same step. Every text through `lang()` with `php tools/tr_add.php`.
