---
name: workspace-channel-icons
description: Plan (2026-10-23, not started, answers open): a channel gets an icon its manager picks from a curated Bootstrap Icons list; kind and customer marks stay visible; colour and icon over the API
---

## What is asked? (owner, 2026-10-23)
- "Assign an icon to channels."

## What is there today?
- The icon is derived, never chosen: `ws_channel_icon()` (includes/workspace/customer.php) gives `bi-lock` (private), `bi-door-open` (guest), `bi-person-badge` / `bi-building` / `bi-person-vcard` (customer kinds), else `bi-hash`. It reaches the panel's channel brief as `icon` (`ws_channel_brief()`, channels.php) and the rail (nav.php); the API channel object (`ws_api_channel_present()`, api_resources.php) has neither `icon` nor `color`.
- The screen draws `channel.icon` in the sidebar and the header (assets/js/workspace.js, `ws-ch-icon`), with its own fallback.
- Channel colour (2026.4.5, 5.82): `ws_channels.color` TINYINT, the 20-colour `ws_palette()` (groups.php), `colorPicker()` in the channel form (workspace.js), probe `ws_channel_colors_ready()`.
- Gap: `workspace.channels.create` / `.update` (includes/workspace/api.php) take no `color` for a channel; channel groups do (`ws_palette_hexes()` enum).
- Bootstrap Icons font is served to the panel (assets/fonts/bootstrap-icons, about 2000 icons).

## What is proposed? (not approved yet)
- New column `ws_channels.icon` VARCHAR(40) NOT NULL DEFAULT '' holding a `bi-*` name from a curated list `ws_channel_icon_choices()` (new, about 80 icons with Turkish and English search words: megaphone, cart, truck, receipt, gear, code, palette, people, calendar, chat, star…). An unknown value is refused on save, never drawn.
- `ws_channel_icon()` returns the chosen icon when set, else today's derived one. The kind stays visible: a private, guest or customer channel with its own icon gets a small corner badge (lock, door, person) on the icon, so the meaning is never lost.
- Picker beside `colorPicker()` in the channel form: a searchable grid, a radiogroup for the keyboard, "none" brings back the derived icon. Same rights as editing the channel's name.
- Drawn wherever the channel icon is today: sidebar, header, rail, palette results, # picker, channel board header. Icons stay `aria-hidden`; the name is the label.
- API: `icon` (enum of the choices, empty clears) and `color` (palette hex, null clears) on channels create and update, `dry_run` as today; `ws_api_channel_present()` and `ws_api_channel_schema()` add `icon`, `icon_chosen` (bool) and `color`. There is no channel webhook today; none is added for this.

## Slices
1. No schema: `color` on `workspace.channels.create` / `.update` through `ws_channel_update()` (it already writes `color`) and `color` (hex) plus the derived `icon` in the API channel object; `tools/check_api_schema.php` passes.
2. Schema, ask first: the `icon` column (next free Workspace label, probe with `pg_schema_has()`), `ws_channel_icon_choices()`, `ws_channel_icon()` reading it, the picker, the corner badge, tr.json words.
3. No schema: `icon` over the API and MCP; channel templates (`ws_templates` body) may carry icon and colour, applied by `ws_template_apply()`.
4. Optional: groups get an icon the same way (`ws_channel_groups`), only if asked.

## Open questions (recommendation)
1. Icon source: (a) curated Bootstrap Icons list, (b) any emoji, (c) both → a; an emoji already fits in the channel name.
2. Private/guest/customer channel with its own icon: (a) corner badge keeps the meaning, (b) only public channels may choose → a.
3. Who may choose: (a) whoever may rename the channel, (b) admins only → a.

## Where to start?
- Slice 1 (API colour parity) needs nothing; slice 2 after the owner says yes to the column.
