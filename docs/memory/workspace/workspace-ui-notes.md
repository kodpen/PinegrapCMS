---
name: workspace-ui-notes
description: Where the Workspace screens are drawn and styled, its tokens, colour picker vs plan board legend class names, Turkish initials in the rail, what a closed sidebar section lists, cosmetic leftovers
---

## Where is the Workspace drawn?
- The channel screen is built in `assets/js/workspace.js` (`app.start()`, `drawSide()`, `drawCenter()`, `messageNode()`, `composer()`); `workspace.php` prints only the shell and `#ws-root`. The other screens (tasks, board, calendar, timeline, notes, settings, MCP) use `.ws-shell` with the icon rail from `ws_screen_rail()` in `includes/workspace/nav.php`. Strings reach the browser translated through `ws_js_strings()` (`includes/workspace/screen.php`).
- Styles sit in `assets/css/backend.src.css` from about line 9394 (tokens and grid) to about 16568; the `/* /Workspace */` marker in the middle is not the end. Tokens: `--ws-accent`, `--ws-accent-rgb`, `--ws-soft`, `--ws-radius`, the layout variables `--ws-ch`, `--ws-group`, `--ws-thread-w`. The dark theme is the panel's (`data-bs-theme`); the Workspace only re-tints its accent.

## Which class names look alike but are not?
- `.ws-swatch` is the colour picker's button (`colorPicker()` in workspace.js; 1.75rem rounded square, flex-centred, `.ws-swatch-none` for "no colour"). The plan board's legend dots are `.ws-legend-swatch` with the `-ok/-full/-over/-away` modifiers (`startBoard()`). Until PR #519 the legend also used `.ws-swatch`, and its later rule made the picker's buttons 1.5rem circles with the "none" icon off centre.
- `.ws-avatar` rules exist (~15346, ~15493) but `avatar()` in workspace.js adds no class to the `<img>` it returns; the avatar component becomes Bootstrap 6 Avatar (docs/memory/plans/bootstrap-6-components.md, step B9j).

## How is a channel's initial drawn in the rail?
- `nav.php` shows up to eight pinned channels by their first letter through `pg_initials_upper()` (`includes/fn/contacts.php`), which turns `i` into `İ` and `ı` into `I` when the interface language is Turkish; `mb_strtoupper()` alone gave "I" for "iletişim" (fixed in #519). Initials for people come from `pg_initials()` the same way.

## What does a closed sidebar section still list?
- Section headings come from `sectionToggle()` in `drawSide()`; the closed ones are `ws-side-closed` in localStorage (`closedSections()` / `closeSection()`), read on every draw, so the ws_sync redraw and a reload keep them. A closed channel section still lists the channel on the screen. A closed Discussions section (`closedParts.threads`) lists none: it used to keep the open discussion, which reopens from `&thread=` in the address and sorts first, so with one discussion the caret turned and nothing hid.

## What is known and left for the UI plan?
- Keyboard focus rings are switched off with `!important` (~9422), the accent is typed twice (hex and RGB), the first "Loading…" sits in the sidebar column of the grid, `<img src="">` is drawn for a person without a picture, and several rule blocks are duplicated (`.ws-channel-link`, `.ws-tabs`, `.ws-center`). All listed in the plan; none changes behaviour.
