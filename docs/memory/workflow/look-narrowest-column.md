---
name: look-narrowest-column
description: Before any look, a step that draws in a message row or a side panel posts pictures in its narrowest column with its longest content (owner's words 2026-11-16); what a set must hold
---

# A look starts in the narrowest column

## What did the owner say?
- Owner (2026-11-16, typed to the architect, with a picture of an agent state line broken into one word a line in a discussion): "tartışma alanında fark ettim ama muhtemelen aynı sorun kanal sohbetlerinde de var. başta daha dikkatli tasarımlar yapılsın sonradan tekrar tekrar aynı yere geri dönmeyelim. bu ve bunun gibi sıkışıklıklar düzelsin ve modernleşsin".
- The fault and the sweep: issues #10029 (the state line) and #10030 (every part a message carries, in the discussion panel's width and at 390).

## What is the rule? (architect's working rule, 2026-11-16)
- A step that draws anything in a message row, a card, a toolbar or a side panel posts, before any look, pictures of the part in the narrowest column it can stand in, with the longest content it allows.
- The narrowest column is not only the phone: in the Workspace it is the discussion panel on a wide window, then 390. A part is sized by its column's width, never by the window's.
- The longest content: the longest Turkish label, a name at its limit, a note or comment at its limit, one unbroken long address, the largest count, and the list at the size where it wraps or scrolls.
- The architect's first look refuses a set without these pictures, and no sheet goes to the owner while a word is cut, a label breaks one word a line or a control runs out of its column.

## What does a good narrow part do?
- The thing the part exists for (a name, a count, a time) is the last to be cut; the helping word goes first, kept in the title and the accessible name.
- A long free text gets its own line across the column and wraps between words; a row of buttons wraps to a row each by the row's own width; a range or tab control fits the column with no cut word.
- A table that cannot fit stacks its cells under each row's label instead of scrolling sideways; a popover on the desktop is a bottom sheet at 390 that never grows past the screen.

## Where was it first applied?
- 2026-11-16 and 17: the Customers report (CV2: the groups table stacks, the range control and the chart's legend fit), the follow-up row's two buttons (FW2), the prototype comment mode (PN2: a long thread, the off-page strip with five comments), the "Made by" mark (AW3: the word goes, the name stays).
- The sweep and the state line he asked for: every part a message carries (#10226) and the agent state line (#10032), each accepted by the owner on one sheet on 2026-11-17 (polls 133 and 134, `decisions-made-workspace-38.md`).
