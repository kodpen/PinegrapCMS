---
name: workspace-memory-tab
description: The Memory tab as rows under folding project headings (GM3a): the general memory and the channel's own as two parts, a row's columns, Move to the general memory, the form, ws_memory_list's parts
---

## Why does it look like this?
- Owner (2026-11-19, with a picture of the Claude app's memory list): project headings that fold, under each a row per record. Plan workspace-memory-general, GM3a (#GM3A); the cards' grid of CL3 (#8703) is gone.

## What does ws_memory_list answer?
- `parts`, each `ws_memory_panel_part()`: `key` (`general` | `channel`), `can_post`, `can_move`, `count`, `groups` (`ws_memory_groups()`: projects A–Z with case aside, No project last, each group's records pinned first then newest). Every record carries `first_line` (`ws_memory_first_line()`: the first non-blank line, spaces folded, cut at 160 characters with "…").
- In an agent channel, for whoever keeps the general memory (`ws_memory_brief_general()`), the general part comes first and the channel's part has `can_move`. With `general=1` the general part comes alone.

## How is a part drawn?
- `section.ws-memory-part`, a card: its head (`h3.pg-title-status`, General memory with "Shared by every agent channel." or This channel's memory) and New entry (`pg-button-fill` when it is the only part, with the channel's hint; `-quiet` with two parts, the hint then above both).
- An empty part shows its `.ws-empty` (Nothing in the general memory yet / in this channel's memory).

## How are headings and rows drawn?
- `details.ws-memory-project` per project, open; a fold is kept while the tab stays open (`folded`, not stored). Its summary: chevron (turns 90° open), the name or No project, the count as a `pg-pill`.
- `.ws-memory-row`, a grid: pin mark, name (Expired pill; the row at 75% opacity), the first line (one line; the whole text as its title), tags as `pg-pill`, the change date (author, changer and expiry in its title), then `pg-icon-button`s: Move to the general memory (`ws_memory_move`, asks first), Edit, Delete. Below a 40rem pane (`container-type` on `.ws-memory`) tags and date go under the name.

## What does the form hold?
- From the kit's form parts (`h.form`): Title, Project (a datalist of the projects already listed), Text, Tags, Expires on, the Pinned switch, Cancel and Save. A general record is written with `general=1`. `/memory` without a text opens it for the channel. No `btn btn-` is left in the file (check_look baseline lowered).

## How is it tested?
- `tests/workspace_channel_memory_test.php` (`first_line`, `groups`, the parts' wiring), `tests/workspace_channel_look_test.php` (`test_workspace_channel_panes_memory_is_rows_under_projects`).
