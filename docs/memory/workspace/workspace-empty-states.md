---
name: workspace-empty-states
description: Workspace empty lists with a small theme-coloured drawing (emptyState(), the shared ART kit / window.PGWsArt) and skeleton rows instead of "Loading…" (U4, PR #2027)
---

## How is an empty list drawn?
- `emptyState(kind, text, extra)` in `workspace.js` (PR #2027): a small drawing plus one line, kinds `channels` (a section of the main menu's Workspace list, handed in on the channel screen as `hooks.emptyState`; elsewhere the list draws the line alone; extra `ws-empty-side`), `tasks` (`taskRows()`), `inbox` (inbox zero), `notes` (the notes screen when there is no note at all, above the privacy intro). Before, these were grey text with a Bootstrap icon. Other `.ws-empty` lines stay text.

## Where do the drawings come from?
- The drawings come from one kit, `ART` in `workspace.js`, exposed as `window.PGWsArt`; `workspace_tour.js` (served after `workspace.min.js`) draws its steps with it instead of its own copy. Colours are the `.pg-tour-art` classes of `pg_tour.css` (loaded on every panel screen, so light and dark follow the theme); an empty list's SVG is 120×80, class `pg-tour-art ws-empty-art`. A new empty kind goes into the kit's `EMPTY` map.

## What replaces "Loading…"?
- `skeleton(rows, extra)` replaces every "Loading…" line in `workspace.js`: grey rows (`.ws-skeleton-row`, a dot and a bar) with `role="status"`, `aria-busy` and the word kept in a `visually-hidden` span; `ws-skeleton-sm` for drawers and small slots. The box is removed by whatever already cleared the loader.
- The channel screen's first rows (`workspace.php`) and its start-up error carry `ws-boot`: `.ws-app > .ws-boot { grid-column: 1 / -1 }`. The other workspace screens still print a "Loading…" line in PHP.
- Motion (skeleton shine, the drawing's fade-in) only inside `@media (prefers-reduced-motion: no-preference)`.

Related: docs/memory/workspace/workspace-ui-scale.md, docs/memory/plans/workspace-friendly-ui.md
