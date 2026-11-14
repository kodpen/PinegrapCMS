---
name: workspace-channel-identity-motion
description: Workspace channel shell as one card in the channel colour (wash, pill tabs), day chip, writing box card (CL1 #8678); group colour chips; send pop, bounce, slide-in (#2094)
---

## What is the top of a channel screen?
- One card, `.ws-channel-card`, built by `drawCenter()` in `assets/js/workspace.js` (#8678): the header (`.ws-head.ws-head-channel`, classes kept for the tour and the menu), then the rows that apply, in order: audit or join bar, guest bar, share bar, era bar, then the tabs. No rule between rows; each bar is a tinted borderless row.
- The share bar is a row only while a link is open (`shareBar()` returns null otherwise): a warning row with a 4px edge and its mark in a filled circle (`.ws-share-bar-open`). With no link open, a small `bi-exclamation-circle` button (`.ws-head-share`, first in the head's actions, on a phone too) says "This channel was shared outside the team; no link is open now." and opens the same Manage (GV6 #9439; owner: the closed row took room and grew the head, `workflow/decisions-made-workspace-24.md`).
- A share change while the screen is open runs `drawShareNotice()`: the old row and button go, the row lands before `.ws-era-bar, .ws-tabs` inside the card and the button in the head. Tests: `tests/workspace_share_notice_test.php`.
- The card has a margin of `.75rem` (`.35rem` on a phone) and a `.875rem` radius, and fills the width (no max width). On a phone with the keyboard up its tabs hide (`body.ws-keyboard .ws-channel-card > .ws-tabs`).
- Why: owner (2026-11-10) found the screen "line after line" (the colour stripe, the rule under the header, under the tabs) and approved `plans/workspace-channel-look/01-before-after.jpg`.

## Where does a channel's colour show?
- `drawCenter()` and `refreshHead()` set `--ws-ch` (the brief's `hex`) and `data-ws-colored` on the card, so the tabs see it too. CSS: a tinted border (`--ws-edge` 26% light, 40% dark) and a wash from the sign's side (`linear-gradient(var(--ws-wash-from), …)`: 15% to 5% to clear at 70% light, 22% and 8% dark; `to left` under `[dir="rtl"]`). No top stripe since #8678.
- The icon on `.ws-head-icon` is a soft tile in the colour (`color-mix` 16% light, 26% dark).
- A channel without a colour: a plain card (`--pg-card-bg`, `--pg-border-color`), a grey tile (`--pg-bg-2`, `--pg-fg-2`). The tile is `aria-hidden`; a chosen icon's badge (`workspace-channel-icon.md`) sits inside it. Phone: a 1.5rem tile.

## How do the tabs, the day mark and the writing box look?
- Card tabs are pills (`999px`); the open one is filled with the accent (14% light, 26% dark) or, in a coloured channel, `--ws-ch` at `--ws-tab-fill`; text `--pg-fg-emphasis`. `.ws-tab-mark` counts keep their marks; `:focus-visible` draws an inset 2px ring (the strip scrolls sideways and clips an outer one). The scheduled lists' tabs and the discussion panel head stay as they were.
- `.ws-day` is a centred chip (`--pg-bg-2`, `999px`) without the rules beside it, in the conversation and the discussion panel alike. `#ws-day`, the board's day drawer, is an id and untouched.
- `.ws-composer` has `--pg-card-bg` as background, in the channel, the discussion panel and the guest room.

## Where do group colours show?
- `.ws-group-icon` (the folder in a group heading, drawn by `groupBox()` in workspace_side.js) is a small chip tinted with `--ws-group`; a group without a colour inherits its parent's, else grey.

## How do the motions work?
- `playMotion(node, className)` restarts a one-off class and removes it on `animationend`: `ws-pop` on Send when `send()` reaches `ws_send`, `ws-bounce` on the reaction chip just changed, `ws-msg-in` on a message appended by `sync()` (not on the first load or a scroll back).
- The keyframes run only inside `@media (prefers-reduced-motion: no-preference)`. The card adds no transition.

## How was it checked?
- #8678: sandbox before/after at 1440 and 390 px, light and dark (coloured, plain, share + era channels); tests/workspace_channel_look_test.php pins the source. Dev look after landing.
