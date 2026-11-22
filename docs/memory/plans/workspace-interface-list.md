---
name: workspace-interface-list
description: Plan workspace-interface, the list in words (boards L1 to L3): rows and their marks, the All and Unread filter, Mark all as read, the tools dock (never folded) and More, the keys
---

## How is the list put together? (board L1, top to bottom, 250 px)
- The head as today: back, "Workspace", the gear, the round "+".
- The search field, 32 px, with "Ctrl K" where it opens the Workspace's search.
- A segmented control across the width: "All" and "Unread" with the count of channels and discussions that hold something new.
- Today; "Channels" (pinned first, a hairline, the group tree, the other joined ones, then one row "N channels you could join", then New group for managers); "My discussions". This part scrolls.
- The tools dock, fixed at the list's foot.
- Section names are 12 px in the third text colour, not capitals.

## How is a row drawn? (board L3)
- 30 px high. An 18 px tile in the channel's colour holds `#`, the lock of a private channel or the icon the channel was given; then the name.
- Unread: the name at weight 600 and the count in a quiet pill. A channel that mentions the reader: the number of mentions in the accent (`workspace-interface-details.md` has the rules of the numbers, the filter and the keys).
- A pinned channel shows its pin only when pointed at; pointing shows the pin and the "⋯" menu in the count's place. The open channel has the quiet fill and an accent line at its left.
- Muted (`notify` none): the name in the third text colour, a bell with a stroke, a dot in place of the count.
- A draft kept for the channel (`draftKey()` in localStorage): a pencil at the row's end; it goes when the draft is sent or cleared.
- A closed group shows the sum of its unread counts.
- "N channels you could join" opens the dimmed rows in place with "Join" on each; its state is the person's (`ws-side-open`).

## What does Unread do? (board L2)
- The list keeps Today, the channels and discussions with unread messages inside their groups, and the open channel; empty groups, read channels and the join row go. The choice is the person's (`ws-side-filter` in localStorage) on every page.
- "Mark all as read" stands at the Channels heading's right while Unread is chosen: it asks once (`pgConfirm()`), then reads every joined channel and discussion. Nothing unread: one line "Everything is read".

## How do the tools stand? (boards L1, L2)
- A head line "Tools" with "⋯" (More) at its right; under it cells four across, a 26 px tile in the tool's colour over its short name at 10.5 px; the open screen's cell has the quiet fill. The order is `ws_nav_groups()['tools']`.
- The dock has one state. Owner (2026-11-22): it never folds and never shows as a row of icons without their names; the Tools heading's old fold (`ws-side-closed`, key `tools`) goes.
- My Tasks carries the count of the reader's open tasks due today or before, in the accent; no count at zero.
- More is a card over the list above the dock, listing `more` as rows; Esc or a click outside closes it; it opens by itself on one of its screens, as today.
- In the phone drawer the dock stands at the drawer's foot.

## Which keys move in the list?
- Alt+Up and Alt+Down: the channel before and after the open one, in the list's order, closed groups skipped. Alt+Shift+Up and Alt+Shift+Down: the next one with unread messages. On the channel screen in place, elsewhere by the row's link. Never while a writing box holds a selection.
- The keys are named in the titles of "Unread" and of the Channels heading.

Related: docs/memory/plans/workspace-interface.md, docs/memory/plans/workspace-interface-steps.md, docs/memory/workspace/workspace-menu-list.md, docs/memory/workspace/workspace-menu-tools.md
