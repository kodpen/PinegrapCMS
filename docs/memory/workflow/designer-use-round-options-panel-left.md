---
name: designer-use-round-options-panel-left
description: The Options panel faults use round UR4 found and did not fix (State's Turkish label, the 240 px size chooser, six-segment groups, long labels, focus after Add layer), each with why
---

## Which faults were left, and why?
- The State row's label reads "Eyalet/İl" in Turkish: `_sdT('State')` shares the address key. A new English label is a reworded label, asked under "Decision question" in the PR.
- At 240 the screen-size chooser in a section's head is not one badge as `designer/designer-options-narrow.md` says: XS to XXL run past the card ("Boyut ve görünüm" cuts XXL at 1440 too). Core head markup, a step of its own.
- A group of six one-letter segments (Font size 1 to 6, Gap 0 to 5) puts its last segment outside the group when the row has the per-size chevron: `_sdSegPlan()` measures 162 px without the chevron. A pure function: its own step, test first.
- At 240 a long label breaks inside a word ("Bulanıklaştı/r", Filter's Blur) and a slider is a thumb only beside its 84 px number; "Play on the canvas" is cut; Interactions' rows, drawn by their part, are not measured by `_sdNarrowStack()` ("Sayfalar arasında…" ends in an ellipsis).
- "Add layer" by Enter opens its Colour, Gradient, Picture choice but the focus falls to the page (the part redraws the list); Tab comes back to it. Search on Enter to "Hide on" leaves the focus in the box: the row is drawn by the part after the jump.
- The rows of Text effect, Filter, Cursor are found by the search only while their "More" is open (as built). The colour picker's white field under a light OS theme is the picker's own look (`designer-chrome-measures.md`).
- Outside UR4: the page bar's "Önerilen gruplar" runs over "Tıklama" at 1440 on the 41-page design.

Related: docs/memory/workflow/designer-use-round-options-panel.md
