---
name: workspace-density
description: The Workspace comfortable / compact switch: ws-density key per browser, .ws-compact on <html>, which tokens it narrows, where the switch sits, which screens follow (PR #3733)
---

## What is the density switch?
- A per-browser choice between comfortable (default, the U1 look) and compact, approved by the owner in #3673; built in PR #3733. No schema, no `ws_profiles` column: `localStorage['ws-density']` holds `comfortable` or `compact`; anything else (or no storage) reads as comfortable.
- `assets/js/workspace.js`: `readDensity()`, `writeDensity()`, `applyDensity()` near the top. `applyDensity(readDensity())` runs at the script's top level, before `start()` draws anything, so a compact screen never flashes comfortable first; a change applies at once, without a reload.

## Where is the switch?
- In the header of Today, the first page (`showHome()`, `?view=home` on workspace.php): an icon button (`densityButton()`, `bi-arrows-collapse-vertical`, title "Density") opening a `ctxMenu()` with "Comfortable" / "Compact" as checked radio items. Not in the channel menu: that menu holds only the channel's own settings (workspace key rules).
- Strings `density`, `density_comfortable`, `density_compact` in `ws_home_js_strings()` (includes/workspace/home.php); Turkish "Yoğunluk", "Ferah", "Sıkı".

## What does compact change?
- One block in backend.src.css, right after the dark-theme token block: `html.ws-compact body.workspace, html.ws-compact .ws-app, html.ws-compact [id^="ws-"]` redeclares tokens only: `--ws-space-1..4` (.15/.35/.5/.65rem instead of .25/.5/.75/1rem), `--ws-row-y` (.1rem, was .3rem), `--ws-head-y` (.35rem, was .6rem), `--ws-head-h` (2.8rem, was 3.4rem). No per-element override; `tests/workspace_density_test.php` fails if the block sets anything but `--ws-*`.
- The three row/header tokens were added to the scale for the paddings it did not cover: `.ws-msg` and `.ws-channel-link` rows (`--ws-row-y`), `.ws-head` (padding and min-height). `.ws-pane`, `.ws-home`, `.ws-side-head`, `.ws-side-scroll` now read `--ws-space-*`. These rules carry the comfortable value as `var()` fallback, because some of them may draw outside a token scope.
- Left literal: the outer frame of the non-channel screens (`#ws-root`, `.ws-shell-main` and the toolbar's negative margins; `tests/workspace_screen_frame_test.php` reads them), and the phone block's own `.ws-pane` and composer paddings (already tight).

## Which screens follow it?
- Every page that loads workspace.min.js: channels (overview, channel, discussions), tasks, board, calendar, timeline, notes, flows, channel templates, and a record screen once its Workspace drawer script is fetched. Workspace settings and MCP connections do not load it and stay comfortable; so does the main menu's Workspace list on non-Workspace pages (workspace_side.js does not read the key).
- Not drawn in a browser when built: light/dark need nothing (only spaces change), and no motion was added, so `prefers-reduced-motion` is untouched.
