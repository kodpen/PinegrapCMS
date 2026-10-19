---
name: workspace-ui-scale
description: The Workspace's CSS scale (radii, spaces, avatar sizes, shadows as --ws-* tokens), the keyboard-only focus rings, the dark tones of the nav icons, where the block ends in backend.src.css
---

## Which tokens does the Workspace measure with?
- Declared once on `body.workspace, .ws-app, [id^="ws-"]` in `assets/css/backend.src.css` (PR #531): `--ws-radius-xs .45rem` (chips, badges, small rows), `--ws-radius-sm .65rem` (buttons, inputs, list rows, menus), `--ws-radius 1rem` (cards, composer, bubbles), `--ws-radius-lg 1.25rem` (drawers, overview cards); `--ws-space-1..4` (.25/.5/.75/1rem); `--ws-avatar-xs 1rem`, `--ws-avatar-sm 1.6rem`, `--ws-avatar 2.2rem`, `--ws-avatar-lg 3rem`; `--ws-shadow` (soft, grey) and `--ws-shadow-accent` (tinted with the accent, for what is lifted or focused), both re-tinted for the dark theme beside `--ws-accent` and `--ws-accent-rgb`.
- Every `border-radius` in the block that was a rem value between .3 and 1 now names a token; `50%`, `999px`, `0` and the sub-.3rem ones stay literal. The avatar rules (`.ws-avatars img`, `.ws-msg-avatar img`, `.ws-task-note-avatar`, `.ws-avatars-sm img`, the notes faces) read the avatar tokens. A new rule takes a token, not a number.
- `--ws-accent-rgb` is `--workspace-color` written out in numbers for `rgba()`; CSS cannot derive the one from the other, so the two sit together in the same block and are changed together.

## How do focus rings work?
- They follow the keyboard, not the mouse: `*:focus:not(:focus-visible)` clears outline and shadow (a click leaves no ring, the complaint that had switched rings off entirely), `*:focus-visible` draws a 2px accent outline with a 2px offset. Before #531 every ring was off with `!important`, so the Tab key had nothing to show. Scoped to `body.workspace`, `[id^="ws-"]`, `.ws-ctx`, `.ws-ask-modal`.
- The composer is the exception: a text field (the `.ws-rich` contenteditable, the textarea fallback) matches `:focus-visible` on a mouse click too, so the frame (`.ws-composer:focus-within`, accent border) is its only focus mark and the field inside draws no ring (#635). Before, every click into the writing box drew a second ring inside the frame.

## What else is on the scale?
- Drawers (`.offcanvas-end` in the workspace or with a `ws-` id) have their inner corners on `--ws-radius-lg`, no hairline, the soft shadow; the overview's cards the same; the composer sits on `--ws-shadow` and lifts to `--ws-shadow-accent` while written in; context menus on `--ws-radius-sm`. The nav icon tones (`[data-ws-nav]`) have lighter twins under `html[data-bs-theme="dark"]`.
- The workspace CSS runs from the `/* ── Workspace ── */` marker to the `/* ── /Workspace ── */` marker at the end of the file; the feature add-ons (status, templates, time, links, delegation, approvals, acks) are inside it.

## What is next?
- The avatar component and presence dot (U2), empty states and skeletons (U4), motion (U5), density (U6): docs/memory/plans/workspace-friendly-ui.md.
