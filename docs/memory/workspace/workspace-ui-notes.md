---
name: workspace-ui-notes
description: Where the Workspace screens are drawn and styled, its tokens, colour picker vs plan board legend class names, what a closed section of the channel list shows, cosmetic leftovers
---

## Where is the Workspace drawn?
- The channel screen is built in `assets/js/workspace.js` (`app.start()`, `drawCenter()`, `messageNode()`, `composer()`); `workspace.php` prints only the shell and `#ws-root`, a grid of the channel (and an open discussion) alone. The other screens (tasks, board, calendar, timeline, notes, settings, MCP) use `.ws-shell` / `.ws-shell-main`. The list of channels and places is the main menu's Workspace panel on every screen (`assets/js/workspace_side.js`, `workspace-menu-list.md`). Strings reach the browser translated through `ws_js_strings()` (`includes/workspace/screen.php`); the list takes its words from the same sets (`ws_side_js_strings()`).
- Styles sit in `assets/css/backend.src.css` from the `/* ── Workspace ──` marker (about line 10756) to the end of the layer; the `/* /Workspace */` marker in the middle is not the end. Tokens: `--ws-accent`, `--ws-accent-rgb`, `--ws-soft`, `--ws-radius`, the layout variables `--ws-ch`, `--ws-group`, `--ws-thread-w`. The dark theme is the panel's (`data-bs-theme`); the Workspace only re-tints its accent.

## Which class names look alike but are not?
- `.ws-swatch` is the colour picker's button (`colorPicker()` in workspace.js; 1.75rem rounded square, flex-centred, `.ws-swatch-none` for "no colour"). The plan board's legend dots are `.ws-legend-swatch` with the `-ok/-full/-over/-away` modifiers (`startBoard()`). Until PR #519 the legend also used `.ws-swatch`, and its later rule made the picker's buttons 1.5rem circles with the "none" icon off centre.
- `avatar()` in workspace.js returns a Bootstrap 6 `span.avatar`, not an `<img>`; a screen sizes it with `--bs-avatar-size` on `<its class> .avatar`, never with `img` rules. `.ws-avatar` is not a class (`ui/panel-avatar.md`).

## What does a closed section of the channel list still show?
- Section headings come from `sectionToggle()` in `workspace_side.js`; the closed ones are `ws-side-closed` (groups `ws-groups-closed`) in localStorage, read on every draw and shared by every page, so the ws_sync redraw, a reload and another screen keep them. A closed channel section still lists the channel on the screen, a closed Work or Decisions section the screen that is open. A closed Discussions section lists none: it used to keep the open discussion, which reopens from `&thread=` in the address and sorts first, so with one discussion the caret turned and nothing hid.
- People's initials come from `pg_initials()` / `pg_initials_upper()` (`includes/fn/contacts.php`), which turn `i` into `İ` and `ı` into `I` in Turkish (#519).

## What is known and left for the UI plan?
- Keyboard focus rings are switched off with `!important` (~9422), the accent is typed twice (hex and RGB), `<img src="">` is drawn for a person without a picture, and several rule blocks are duplicated (`.ws-channel-link`, `.ws-tabs`, `.ws-center`). All listed in the plan; none changes behaviour.
