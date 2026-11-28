---
name: workspace-memory-tab
description: The Memory tab (GM3a, MS3): the general and the channel's part as rows under folding project headings, the search box, a row's columns, Move, the form drawer, ws_memory_list's answer
---

## Why does it look like this?
- Owner (2026-11-19, with a picture of the Claude app's memory list): project headings that fold, under each a row per record. Built in GM3a (#10425); the cards' grid of CL3 (#8703) is gone. The same rows, the general part alone, fill the General memory drawer (GM3b #10440, `channel-memory-general.md`).

## What does ws_memory_list answer?
- `parts`, each `ws_memory_panel_part()`: `key` (`general` | `channel`), `can_post`, `can_move`, `count`, `more` (a search found more than it shows), `groups` (`ws_memory_groups()`: projects A–Z with case aside, No project last, each group's records pinned first then newest). Every record carries `first_line` (`ws_memory_panel_line()`: without a search `ws_memory_first_line()`, the first non-blank line, spaces folded, cut at 160 characters with "…"). `search` true when the words given held one.
- In an agent channel, for whoever keeps the general memory (`ws_memory_brief_general()`), the general part comes first and the channel's part has `can_move`. With `general=1` the general part comes alone.

## How does the tab's search work?
- A `pg-toolbar` with the search box (Search the memory, `ws-bar-search`) above both parts, built in MS3 (#11882). Typing waits 300 ms, then `ws_memory_list` is asked again with `search=<words>`; an answer overtaken by a newer search is dropped. The words stay while the tab is open.
- The server matches as the API search does (`ws_memory_search_page()`, `channel-memory-search.md`): every word in the title, text or tags, the expired records too, `WS_MEMORY_PANEL_SEARCH` (100) records a part at most; `more` true adds "Only the first matches are shown. Add a word to narrow them." under the part.
- With a search a row's line is the text around the first word found, without one the first line (`ws_memory_panel_line()`, 160 characters). A part with no hit says No entry holds these words. The form's project list is kept from the last list without a search.

## How is a part drawn?
- `section.ws-memory-part`, a card: its head (`h3.pg-title-status`, General memory with "Shared by every agent channel." or This channel's memory) and New entry (`pg-button-fill` when it is the only part, with the channel's hint; `-quiet` with two parts, the hint then above both).
- An empty part shows its `.ws-empty` (Nothing in the general memory yet / in this channel's memory).

## How are headings and rows drawn?
- `details.ws-memory-project` per project, open; a fold is kept while the tab stays open (`folded`, not stored). Its summary: chevron (turns 90° open), the name or No project, the count as a `pg-pill`.
- `.ws-memory-row`, a grid: pin mark, name (Expired pill; the row at 75% opacity), the first line (one line; the whole text as its title), tags as `pg-pill`, the change date (author, changer and expiry in its title), then `pg-icon-button`s: Move to the general memory (`ws_memory_move`, asks first), Edit, Delete. Below a 40rem pane (`container-type` on `.ws-memory`) tags and date go under the name.

## What does the form hold?
- It opens in a form drawer beside the list: `workspace-memory-form-drawer.md`.

## How is it tested?
- `tests/workspace_channel_memory_test.php` (`first_line`, `groups`, the parts' wiring), `tests/workspace_memory_search_test.php` (`ws_memory_panel_line()`, the search's wiring), `tests/workspace_channel_look_test.php` (`test_workspace_channel_panes_memory_is_rows_under_projects`).
