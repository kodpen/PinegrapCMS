---
name: workspace-channel-board-look
description: Channel Board tab look (BM3 #11670): the four figures (Open tasks, Due this week, Overdue, Done in the last 7 days) as filters, columns, task cards, due chip, drop place
---

## What are the four figures?
- A "Figure grid" above both views (`figuresNode()` in workspace_board_channel.js, `.ws-cb-figures`): "Open tasks", "Due this week" (today to Sunday), "Overdue" (rose), "Done in the last 7 days" (green, `WS_CHANNEL_BOARD_FIGURE_DONE_DAYS`).
- Counted on the server, pure: `ws_channel_board_figure_keys()`, `ws_channel_board_card_figures()`, `ws_channel_board_figures()` in `includes/workspace/channel_board.php`; tests `tests/workspace_channel_board_figures_test.php`. They count what the toolbar lets through.
- A figure is a button (aria-pressed, `active` in the primary's subtle fill): it narrows both views to its tasks (`figure` of `ws_channel_board` and `ws_channel_tasks`, `ws_channel_board_figure_filter()` / `ws_channel_board_list_figure()`); a second press clears it. The other figures keep their counts.
- The List view reads them alone with `figures_only=1`. A figure looks past open / closed in the List view.
- At phone width (under 576 px) they are one row that scrolls sideways.

## How is a column drawn?
- On `.ws-pane-board` a column is a gray surface (`--pg-bg-1`, 16 px corner, no border), growing from 17rem to 34rem; the cards stand on it 9 px apart. Under 768 px the columns scroll sideways, each `min(292px, 82vw)`.
- Head: the status's dot (`.ws-cb-dot-<status>`), the name, the count in a pill; "New task in this column" at its foot (`.ws-cb-add-foot`). "Done" keeps "n finished earlier".
- The workflow table's board (`.ws-wt-board`, `mountSource()`) keeps its own columns and cards.

## How is a card drawn?
- First line: the number, the repeat mark, a green check on a done card (gray title), the priority chip with a flag at the right ("Urgent" rose, "High" amber). The title, at most three lines; tags as gray chips.
- Last line: the due chip, the steps as "3/5" with a short bar, the notes' count, the faces. The due chip's tone comes from `ws_channel_board_due_chip()` (pure): gray later, amber today and tomorrow, rose "n days late", green on a closed task; the card carries `due_tone` and `due_late`.
- 13 px corner; nothing new is stored, every part is a value the card had.

## What shows while a card is dragged?
- The card is lifted (`.ws-cb-lifted`: float shadow, turned 2 degrees). The column under the pointer shows its dashed drop place (`.ws-cb-slot`): "Drop here: status becomes “name”", "goes to <person>", "nobody on it", "due date becomes …" or "no due date" by grouping. Moves themselves are as before (pointer, keyboard, the phone picker).
