---
name: designer-options-narrow
description: The Options panel under 300 px (drawn at 240, owner 2026-11-17): widths, the identity row, tabs, wrapped labels, stacked rows, quick strip, closed sections, the size chooser, shorter texts
---

## What is decided?
- Owner (2026-11-17, agent channel poll 117, message 7593): "Evet, böyle çıksın" on the 240 px drawing (`options-5-narrow-column.jpg`, in git history: `designer-panels-overview.md`). The parts and their order stay; only what does not fit changes place. At 300 px and above the panel is the wide one.
- The column marks itself `sd-rcol-narrow` from its own width (`designer-right-column.md`).

## Which widths at 240?
- A section card 216, a row's inside 192; the label column 78, a gap of 10, the control 104. Every target keeps 24 by 24.

## What changes, part by part?
- Identity: the name on one line with an ellipsis; the tag badge never cut. The path shows its last step after "… ›", the whole path in the tip. One icon button, the three dots, whose menu starts with "Copy style" and "Paste style".
- Tabs carry no icon; a tab's badge sits on its top right corner (16 px, a 2 px ring of the holder's ground) and takes no width.
- A label wraps to two lines at most; the dot stays at the first line.
- Quick strip: three cards as wide as their value, the last taking the rest; no caption line (it is the tip and the name); alignment as its icon alone.
- A closed section's name is never cut; its summary takes what is left; with nothing left only the colour square and the count stay.
- The screen-size chooser in a section's head is one badge with a chevron that opens the five sizes. The spacing box keeps its drawing with side paddings of 27.
- Shorter texts: "What I changed" for the filter, "Search or add…" for the search.

## When is a row stacked?
- The label on its own line, the control under it at the full 192, when the control is a group of four or more written segments, does not fit 104, or the label needs more than two lines. Up to four icon segments stay beside the label. How it is measured: `designer-options-controls.md`.
- A group keeps one line of segments. An invalid field's line spans the row and starts under the label.
