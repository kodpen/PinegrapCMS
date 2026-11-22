---
name: workspace-menu-unread
description: The Workspace list's All / Unread filter (board L2): which rows Unread keeps, ws-side-filter, Mark all as read (ws_read_all), "Everything is read", the keys Alt+Up/Down and Alt+Shift+Up/Down
---

## What does the filter draw?
- `.ws-side-filter`, the look's `pg-seg` across the list under the search field: "All" and "Unread", the chosen one `aria-pressed="true"`. Unread carries `.pg-seg-count`, the number of rows holding something new (not of messages); its title names the keys (`side_keys`).
- The choice is the person's on every page: localStorage `ws-side-filter`, `unread` or `all` (`readFilter()` / `writeFilter()` in `assets/js/workspace_side.js`).

## Which rows does Unread keep?
- `sideUnread(item, isThread)`: a joined channel with `unread` above zero, a muted one (`notify` none) only when it has `mentions`; a discussion with `unread` above zero. The open channel and the open discussion stay listed even when read.
- Today stays. Pinned, groups and the other joined channels keep only such rows. A group is drawn only when something under it is listed (`groupListed()`), and its fold is set aside (the toggle is disabled). The join row, New group and the empty-group line go; a channel not joined shows only while it is open.
- Section folds (Channels, My discussions) are kept as the person set them.
- Nothing unread: one line "Everything is read" (`.ws-side-all-read`, `role="status"`) under the Channels heading.

## What does Mark all as read do?
- A ghost `pg-button-sm` at the Channels heading's right, only while Unread is chosen and something is new. `pgConfirm()` asks "Mark N channels as read?" (Mark as read / Cancel); then the panel action `ws_read_all`, then the list is asked again: on the channel screen through `hooks.reload` (`reloadChannels()`, which now also takes `threads`), elsewhere `ws_side` again with the old rows kept until the answer.
- After it the focus goes to the Unread segment. The dialog closes a widened rail or the phone's drawer; once it is answered the list is opened again (`menu().openPanel()`) when it is hidden.
- `ws_read_all($viewer)` in `includes/workspace/channels.php`: every `ws_channel_members` row of the reader whose channel is not archived and not a mailbox room, with a message past its mark; each through `ws_can_read_channel()` and `ws_channel_mark_read()` up to the channel's newest message, so inbox and bell rows are read as when the channel is opened. Muted channels too.
- It gives no read receipt, answers nothing and writes nothing else. The pure part is `ws_read_all_marks()` (`tests/workspace_read_all_test.php`). No API route.

## How do the keys move?
- One `keydown` listener in workspace_side.js: Alt+Up / Alt+Down the row before / after the open one; with Shift the next holding something new (`sideStep()`, `tests/workspace_side_unread_test.php`). Nothing past the first or last row; with nothing open they start from the top.
- The rows walked are those the last draw listed (`state.order`): joined channels in drawn order (closed groups and channels not joined skipped), then My discussions in their own order (latest first), not the ranked order they are drawn in, which changes as one is opened. With Unread, only what is listed.
- On the channel screen in place (`hooks.openChannel`, `hooks.openThread`), elsewhere by the row's address. Before the list's data has come the key asks for it (`ws_side`) and steps after.
- Not taken while `pgUI.anyOpen('dialog'|'drawer')` or the Flows editor (`.wsf-app` shown), from a select or a date/time/number field, or while a writing box holds a selection; Ctrl or Cmd with it leave it to the page.

## Why?
- Owner (2026-11-22, plan workspace-interface): unread was told by bold text and a grey count alone; nothing listed only what is new, and nothing read it all at once.

Related: docs/memory/workspace/workspace-menu-rows.md, docs/memory/workspace/workspace-menu-tools.md, docs/memory/workspace/workspace-menu-list.md
