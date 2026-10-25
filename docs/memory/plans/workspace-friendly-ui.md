---
name: workspace-friendly-ui
description: Plan (2026-10-19) for a friendlier Workspace look: token scale, channel colour in the header, empty-state art, motion (avatar moved to bootstrap-6-panel); no schema; slices U1-U6 and open questions
---

## What is this plan?
- Owner request (2026-10-19): a warmer, "cuter" Workspace ("daha sevimli arayüz") without changing how the module works. The channel screen is drawn by `assets/js/workspace.js` (`app.start()`, `drawSide()`, `drawCenter()`, `messageNode()`, `composer()`); styles sit in `assets/css/backend.src.css` lines ~9394-16568; the other screens use `.ws-shell` with the rail from `includes/workspace/nav.php`.
- Nothing here needs a schema step or a new right. Each slice is one PR; `workspace.min.js` is rebuilt with `php tools/build_js.php` when `workspace.js` changes.

## What was found on 2026-10-19?
- Tokens: only `--ws-accent`, `--ws-soft`, `--ws-radius` (css ~9406); no spacing, radius, avatar or font scale; the accent is typed twice (hex at 137, RGB numbers at ~9410/9418). Dark theme is panel-wide (`data-bs-theme`), the workspace only re-tints its accent.
- Avatars: initials with 8 tones already exist (`pg_initials_avatar_url()` in `includes/fn/contacts.php`); `avatar()` in `workspace.js` ~1847 returns a bare `<img>` without a class and gives `src=""` when a person has no picture (acks.php, status.php, task_work.php fallbacks). The `.ws-avatar` rules at ~15346 match nothing. Tones ignore the dark theme.
- Channel colour (20 matte colours, `ws_palette()` in groups.php) shows on the sidebar icon, the rail tile and group borders, not on the channel header.
- Empty states are grey text (`.ws-empty`, 20 uses); loading is the text "Loading…" in 12 places, no skeletons; two keyframes only; no typing indicator. `workspace_tour.js` has a small theme-aware SVG kit reusable for empty-state art.
- Keyboard focus rings were switched off with `!important` (css ~9422-9435); U1 made them keyboard-only.
- `.ws-swatch` is defined twice (picker ~9928, board legend ~13093); fixed in its own PR.

## What are the slices?
- U1 Tokens and shape — done (PR #531, memory docs/memory/workspace/workspace-ui-scale.md): the token scale, every radius on it, keyboard-only focus rings, rounder drawers, home cards and context menus, the composer's shadows, dark nav tones. The accent RGB stays typed beside the hex (CSS cannot derive it); the two sit together.
- U2 Avatar component: moved to the Bootstrap 6 panel plan (owner decision 2026-10-25), step B9j after the switch: v6 Avatar with text initials, theme colours and a presence dot (`bootstrap-6-components.md`). Claim it as `[plan:bootstrap-6-panel] B9j`, not here.
- U3 Channel identity: the channel colour on the header icon and a thin colour stripe; group colour chips in the sidebar section titles.
- U4 Empty states and loading: friendly one-line copy plus a small SVG from the tour kit for "no channels", "no tasks", "inbox zero", "no notes"; skeleton rows instead of "Loading…"; the first "Loading…" and the start-up error span the grid (`grid-column: 1 / -1`), today they sit in the sidebar column.
- U5 Motion: send-button pop, reaction bounce, new-message slide-in, all inside `@media (prefers-reduced-motion: no-preference)`.
- U6 Density switch (comfortable / compact) kept in localStorage like `ws-side-closed`; no `ws_profiles` column.

## What is still open?
1. How far "cute" goes: rounder + softer + illustrations (default) or also a rounded display font (Google Fonts are not loaded in the panel today; a self-hosted font adds weight).
2. Whether U6 is wanted at all.
3. Whether the owner wants a screenshot round on dev.pinegrap.com after U1 before U3-U5 start (recommended: yes, U1 sets the tone).

## Where to start?
- U4 next (it uses the tokens), then U3, U5, U6 in any order; U2 waits for B9j of the Bootstrap 6 plan. Check on dev in light and dark, phone width (`14027-14160` phone block), and with `prefers-reduced-motion`.
