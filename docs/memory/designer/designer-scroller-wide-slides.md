---
name: designer-scroller-wide-slides
description: The Wide slides option of a scroller row (pg-snap-wide): one large slide per stop, 85vw, centred; the Scroller section, its helper and rule
---

## What does the Wide slides option do?
- Selecting a scroller row (class `pg-snap`, e.g. Cards Row of Scroller: Snap Cards) shows a Scroller section with Slides: Cards / Wide slides (`propsScrollerOptions()`). Wide slides adds `pg-snap-wide` to the row (`_sdScrollerSetWide()`, one undo snapshot, PR #8816); Cards removes it. An option, not a new block.
- Rule `pg-snap-wide` (`_SD_BLOCK_CSS`): `.pg-snap-wide .pg-snap-item { flex: 0 0 auto; width: 85vw; max-width: 100%; scroll-snap-align: center; }`. Items are matched by class, not `> *`, because a canvas wrapper sits between row and item. It joins the User Styles the first time the option is turned on, not at drop (not in `scroller-cards`' needs).
- The buttons' step is the distance between the first two items, so they move one wide slide at a time with no script change.
