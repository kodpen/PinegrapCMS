---
name: workspace-channel-identity-motion
description: Workspace header in the channel colour (icon tile, top stripe), group colour chips, and the send pop, reaction bounce and new-message slide-in (U3, U5, PR #2094)
---

## Where does a channel's colour show on its screen?
- `drawCenter()` in `assets/js/workspace.js` sets `--ws-ch` (the brief's `hex`) and `data-ws-colored` on `.ws-head`, the same pair the sidebar link uses. CSS (`backend.src.css`, beside `.ws-head-title`): a 3px top stripe (`box-shadow: inset 0 3px 0 var(--ws-ch)`) and the icon on `.ws-head-icon`, a soft tile (`color-mix` 16% light, 26% dark) in the colour.
- A channel without a colour keeps a grey tile (`--pg-bg-2`, `--pg-fg-2`) and no stripe. The tile is `aria-hidden`; the badge of a chosen icon (`workspace-channel-icon.md`) sits inside it. Phone width: a 1.5rem tile.

## Where do group colours show?
- `.ws-group-icon` (the folder in a group heading, drawn by `groupBox()` in workspace.js and by `nav.php` on the other screens) is a small chip tinted with `--ws-group`; a group without a colour inherits its parent's, else grey. CSS only: both places were already setting `--ws-group` on the group box.

## How do the motions work?
- `playMotion(node, className)` in workspace.js restarts a one-off class and removes it on `animationend`. Classes: `ws-pop` on the Send button when `send()` reaches `ws_send` (click or Enter; edits and scheduled saves do not pop), `ws-bounce` on the reaction chip just left or taken back (after `react()` redraws the message), `ws-msg-in` on a message appended by `sync()` (not on the first load or a scroll back, so a whole page never slides).
- The keyframes (`ws-pop`, `ws-bounce`, `ws-msg-in`) run only inside `@media (prefers-reduced-motion: no-preference)`; with reduced motion the class stays on the node and does nothing.

## What was not seen?
- Checked on a static page with the panel CSS in light, dark and 375px; not on a running instance (real channel data, thread pane, reduced-motion switch).
