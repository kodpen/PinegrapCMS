---
name: workspace-channel-head
description: The channel header in one line (C1, plans/workspace-channel-layout.md): name and tags, people, search, info, task, menu; a phone keeps back, name, ⋯; the purpose in the info drawer
---

## What is in the line?
- `drawCenter()` (workspace.js): `channelsToggle()`, `.ws-head-title` = the `h2` (tile and `.ws-head-name`, the purpose as its tooltip) and `.ws-head-tags` (the status pill, the department badge, the customer or contact from `customerNode()`), cut with an ellipsis before they push the tools away; `.ws-head-actions` = people (`.ws-head-faces`), search, channel info, new task, the menu.
- The purpose line under the name is gone: the purpose is in the channel's info.

## What does a phone keep?
- Below 768px `.ws-head-wide` (people, search, info, new task) and every tag but the status pill hide; the pill shows its icon only (`.ws-status-text`, `workspace_status.js`). The line is the way back, the name, the pill and ⋯; the tabs stay a scroll row under it.
- The menu's first items, `.ws-head-narrow` (hidden from 768px): channel info, members, search in this channel. A new task is the Tasks tab's button and the / command: a channel-menu item never creates another record (`tests/workspace_channel_menu_test.php`, the workspace key rule).

## What do search and info open?
- Search: `searchChannel()` calls `PGWsPalette.open({ scope: 'here' })`; `open(options)` in `workspace_palette.js` starts on the open channel's scope, any other call keeps the scope the person left.
- Info: `showMembers()` titled Channel info: the purpose, the customer, the department, then Members (n). A member's line no longer ends in " · " when they have no title.

## Why?
- Owner (2026-10-31, `workflow/decisions-made-workspace-5.md`): the drawn design, and on a phone a header of one short line so the messages keep the height.
