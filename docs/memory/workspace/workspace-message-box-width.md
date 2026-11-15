---
name: workspace-message-box-width
description: A channel's message boxes stop at 90rem wide and, on a screen wider than 1700px, take a faint tint of the sender's face colour (data-ws-tone); light 5 %, dark 7 %
---

## How wide does a message box grow?
- `.ws-pane-messages > .ws-msg` stops at `max-width: 90rem` (1440px) in a channel's conversation; the discussion panel (`.ws-thread-pane`) and the Decisions tab are left as they were.
- Below a 1700px screen nothing else changes: at 1440 the pane is narrower than the limit.

## Where does the tint come from?
- `senderTone()` in workspace.js gives the theme `avatar()` draws the sender's face in (`tone` over `AVATAR_THEMES`; a login without a name is `secondary`); `messageNode()` writes it as `data-ws-tone`.
- At `min-width: 1700px` the box takes `color-mix(in srgb, <tone> 5%, transparent)`, 8 % on hover; dark 7 % and 11 %. Tones map to `--pg-*` tokens (accent to `--pg-tone-violet`, inverse to `--pg-fg-emphasis`): no new setting, no schema.
- The tint sits under `:where()` and its hover skips `.ws-msg-highlight` / `.ws-msg-selected`, so those and the flash still show. One sender's grouped lines (`.ws-msg-follow`) join into one box: square corners where they meet.

## Why?
- Owner (2026-11-15): boxes ran on across a wide screen (`workflow/decisions-made-product-55.md`). The width and tint went to him as pictures with a close-up pair.
