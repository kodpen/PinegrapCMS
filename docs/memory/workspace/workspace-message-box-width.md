---
name: workspace-message-box-width
description: Channel message boxes: 90rem wide at most, .5rem apart (grouped lines joined), a faint sender tint over 1700px (data-ws-tone; light 1.6 %, dark 2.2 %)
---

## How wide does a message box grow, and how far apart are they?
- `.ws-pane-messages > .ws-msg` stops at `max-width: 90rem` (1440px) in a channel's conversation; the discussion panel (`.ws-thread-pane`) and the Decisions tab are left as they were.
- Each box has `margin-bottom: .5rem` at every width. A box followed by a grouped line of the same sender (`.ws-msg-follow`) has none, so a group stays one box; the gap falls between groups.

## Where does the tint come from?
- `senderTone()` in workspace.js gives the theme `avatar()` draws the sender's face in (`tone` over `AVATAR_THEMES`; a login without a name is `secondary`); `messageNode()` writes it as `data-ws-tone`.
- At `min-width: 1700px` the box takes `color-mix(in srgb, <tone> 1.6%, transparent)`, 2.6 % on hover; dark 2.2 % and 3.5 %. Never `opacity` on the element: it would fade the text too. Tones map to `--pg-*` tokens (accent to `--pg-tone-violet`, inverse to `--pg-fg-emphasis`): no new setting, no schema.
- The tint sits under `:where()` and its hover skips `.ws-msg-highlight` / `.ws-msg-selected`, so those and the flash still show. Grouped lines join into one box: square corners where they meet.

## Why?
- Owner (2026-11-15): boxes ran on across a wide screen (`workflow/decisions-made-product-55.md`); width and tint approved by poll 4863 (#9733).
- Owner (2026-11-15, channel message 5525): a .5rem gap between messages and a fainter tint; he cut one sender's colour to `#4dd0e152` (about 32 % alpha) in the browser, so every tint percentage was scaled by 0.32.
