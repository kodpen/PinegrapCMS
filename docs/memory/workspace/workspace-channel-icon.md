---
name: workspace-channel-icon
description: A Workspace channel's own icon (ws_channels.icon, 11.0.81): the curated Bootstrap Icons list, chosen vs kind icon, the corner badge, who may choose, where it is drawn, colour and icon in the API
---

## What is it?
- Owner ask (2026-10-23): "Assign an icon to channels." Owner choices (2026-10-24): a curated Bootstrap Icons list, not emoji (an emoji already fits in the name); the kind stays visible as a corner badge; whoever may rename the channel may choose.
- Column `ws_channels.icon` VARCHAR(40) NOT NULL DEFAULT '' (2026.11.0, step `upgrade_2026_11_0_workspace_channel_icon()`, label 11.0.81). Probe `ws_channel_icons_ready()` (`pg_schema_has()`). Without the column a sent icon is checked but not written, and the picker is not offered.

## Where is the code?
- `includes/workspace/channel_icons.php`: `ws_channel_icon_list()` (80 rows: class, Turkish words, English words for the search, and the picker's name through `lang()`, its key in tr.json), `ws_channel_icon_choices()`, `ws_channel_icon_clean()`, `ws_channel_icon_check()` (empty clears, anything else outside the list is refused: "Choose the icon from the list."), `ws_channel_icon_chosen()`, `ws_channel_icon_badge()`, `ws_icon_badged_html()`, the picker's list and words.
- `ws_channel_icon()` (customer.php) = chosen, else `ws_channel_kind_icon()` (lock, door, person-badge, building, person-vcard, hash). An unknown or retired stored value is ignored, never drawn.
- Kind marks (hash, lock, door-open, person, person-badge, person-vcard) are not in the list: a lock chosen for a public channel would say "private". `bi-building` is in the list and is also the derived icon of a current-account channel.

## Why does the brief carry icon_chosen?
- `ws_channel_brief()` gives `icon` (the outcome), `icon_chosen`, `icon_badge`, `kind_icon`. A brief passed back into `ws_channel_icon_chosen()` is read through `icon_chosen`; without it a derived `bi-building` looked chosen and got a person badge (tests/workspace_channel_icons_test.php).

## What does the badge show?
- Only when a chosen icon replaces the kind's: `bi-lock` (private), `bi-door-open` (guest), `bi-person` (customer: `customer_type` or `contact_id`); a public channel has none. CSS `.ws-icon-badged` / `.ws-icon-badge` (backend.src.css, beside `.ws-swatch`). Icons stay `aria-hidden`; the name is the label.

## Where is it drawn?
- The main menu's Workspace list (`channelIcon()` in workspace_side.js) and the channel header (`channelIcon()` in workspace.js), Ctrl+K results (`ws_palette_channels()` `badge`), the # pickers and channel chips (`ws_refs_resolve()` sets `badge` on a channel ref; `ws_chip_html()`, `chipNode()`, `drawPicker()`), the file manager's "Send to a channel" (`ws_library_channels()`).
- Places that draw lock/hash from `private` alone (home, inbox, acks, approvals) were left as they are.

## How is it chosen?
- `iconPicker()` beside `colorPicker()` in the channel form: a search (Turkish letters folded with `foldText()`, every typed word must start a word of the icon, Enter takes the first match) over a grid with `role="radiogroup"`; one cell in the Tab order, arrows, Home and End move and choose. The first cell is "none" and shows the kind icon (a new channel's follows the kind radio).
- Sent as `icon` by `ws_channel_create`, `ws_channel_update` and `ws_note_channel` (that one now also passes `color`, which it used to drop); `ws_channel_update()` holds it to `ws_can_manage_channel()`, the rename right.

## What does the API do?
- `workspace.channels.create` / `.update` take `color` (enum `ws_palette_hexes()`, nullable) and `icon` (enum of the list, nullable; empty brings back the kind icon); the dry run names them. `WorkspaceChannel` has `color` (hex or null), `icon` (drawn class), `icon_chosen`. MCP tools follow the routes. No channel webhook exists; none was added.

## What about templates and groups?
- Templates: workspace-channel-icon-templates.md. Group icons: not until asked.
