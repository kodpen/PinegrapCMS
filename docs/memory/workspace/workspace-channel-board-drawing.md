---
name: workspace-channel-board-drawing
description: The drawing of a channel's Board tab (BM1 to BM5, built): one sheet of four boards, the board view, the list view, the Automations drawer, phone width
---

## Where is the drawing?
- `workspace/workspace-channel-board-drawing/`: `board.html` with `board-light.jpg` and `board-dark.jpg`. The sheet draws itself with a script from sample tasks; `?theme=dark` gives the dark look, `?f=A` (B, C, D) one board.
- It carries the look's tokens in its own style block, not `ui/look-sheet/sheet.css`, and reads the panel's Inter and icon fonts from `pinegrap/assets/fonts/`.
- The owner chose from these boards on 2026-11-24 (workspace part 51 of the decisions list). He saw them with Turkish labels; the sheet carries the English ones the steps write.
- Where words and the sheet differ the sheet wins (`workflow/architect-draws-new-part.md`). Sample tasks, names and counts are examples. What was built: `workspace-channel-board-tab.md`, `-look.md`, `-automations.md`.

## Which board belongs to which step?
- A, the board view: BM1 the tab, the Segmented control and the toolbar; BM2 the three groupings; BM3 the four figures, the columns, the cards, the drop place; BM5 the "Automations" button and a column's rule line.
- B, the list view: BM1.
- C, the Automations drawer: BM5 over BM4's functions.
- D, phone width (390 px): BM1 and BM3.

## What comes from a Design example and what is new?
- From the Design examples: "Tabs with a state", the "Figure grid", the Segmented control, the chips, the "Drawer", the "Switch rows", icon tiles in the tones blue, amber, rose, green, violet and gray.
- The board's own, redrawn from what it has today: a column with its head, a task card, the dashed drop place, the "n finished earlier" link.
- New and small: a column's rule line (the automations' violet tone, an icon and one line of words) and a rule's row asking for its answer. Neither is a part of its own kind: the first is a quiet note line, the second a Switch row that opens.

## What does the sheet say that the screens file does not?
- Measures: a column's corner 16 px, a card's 13 px, 9 px between cards; a figure's tile 36 px; a card's title in the medium weight at about 14 px, its first and last lines at 11.5 to 12 px.
- The due chip: gray for a later day, amber for today and tomorrow, rose for "n days late", green with a check on a done task. The priority chip sits at the right of the card's first line.
- The dragged card is lifted and turned a little; the drop place shows only in the column under the pointer.
- In the drawer a rule that is on has the primary line around its row. "Turn on" is the drawer's one filled button and is there only while a row asks for its answer.
- At phone width the toolbar is three lines: the Segmented control with "New task"; the grouping chips; the search field with "Only mine", "Filter" and "Automations" as icon buttons that keep their names.

## Which states have no board?
- An empty board and an empty list: the channel panes' dashed empty card as today (`workspace-channel-summary-board-memory.md`).
- The "Filter" popover, the groupings "By person" and "By date", and a figure chosen as a filter (the primary line and the subtle fill, as a chosen chip).
- A room without the flows right: there is no "Automations" button and no rule line.
- Loading and a failed load: as the board does today.
