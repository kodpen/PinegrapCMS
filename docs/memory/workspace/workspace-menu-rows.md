---
name: workspace-menu-rows
description: The Workspace list's rows (#11079, board L3): 30 px with the channel's 18 px tile, count pills, muted dot, draft pencil, pin and "⋯" on pointing, the join fold, section names
---

## How is a channel row drawn?
- `channelLink()` in `assets/js/workspace_side.js`: 30 px high (a little less in the compact density, through `--ws-row-y`), the tile, the name, then `.ws-side-marks` at the end. The CSS sits on the box's class `.ws-menu-side` (not the id), so the Design examples pattern "Workspace list row" draws the same rules.
- The tile is `channelIcon()`: the look's `.pg-icon-tile` at 18 px (`.ws-side-ch-tile`) with `--pg-tone: var(--ws-ch)`, holding `#`, the lock or the channel's own icon and its corner mark. Its icon keeps the ink pulled 75% toward the text colour, 3:1 on every palette colour in both themes (`tests/panel_contrast_test.php`).
- The open row: `--pg-quiet` fill and a 2 px `--pg-accent` line at its left. Pointing: `--pg-hover`.

## Which marks stand at a row's end?
- Unread: the name at weight 650 and the count in the quiet pill (`--pg-quiet`, `--pg-fg-2`). Mentions: their count in `--pg-accent` with `--pg-on-accent-fill` (4.5:1 in both themes). The mentions when there are any, else the unread count.
- Muted (`notify` none, `.ws-side-muted`): the name and tile in the third ink, a struck bell, a grey dot for unread instead of the count; a mention still shows its accent pill.
- A draft kept for the channel or a discussion: a pencil, read through `window.PGWsDraft` (`workspace-unsent-draft.md`) with `ws_side_payload()['draft']` (`site` = the panel's api.php, as `CFG.api_url`; `me` = the reader). A `storage` event from another tab redraws the list.

## What does pointing show?
- Where the screen hands in `pin` or `channelMenu` (the channel screen), the row gets `.ws-side-has-tools`: pointing hides the marks and shows the pin and "⋯" (`ws-side-row-tools`). A pinned row's pin no longer stands there all the time. Both are aria-hidden mouse shortcuts (`workspace-nested-controls.md`).

## Where are the channels not joined?
- Those in no group wait behind one row "N channels you could join" (`.ws-side-join`, word `side_join`); open or shut is the person's (`ws-side-open`, key `join`). Shut, the open channel still shows. Inside a group they stay dimmed in place.
- On the channel screen each carries Join (`hooks.joinChannel`, `ws_channel_join`); elsewhere the row only opens the channel.

## What else changed with the rows?
- Section headings (`sectionToggle()`): the name as written, 12 px, third ink; the chevron at the end shows on pointing, on focus and while closed.
- The pinned part ends in a hairline when more follows.
- The search field reads "Search or go to" (`side_find`), its accessible name too.

Related: docs/memory/workspace/workspace-menu-list.md, docs/memory/workspace/workspace-menu-unread.md
