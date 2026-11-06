---
name: workspace-screen-frame
description: Every Workspace screen but the channel one is padded by .ws-shell-main (no screen list), where sticky columns stop, how the MCP servers card wraps on a phone
---

## Where does a Workspace screen get its side padding?
- From `.ws-shell-main`, on every screen but the channel one: `body.workspace .ws-shell-main { padding: 0 .75rem .75rem }`. A `.row` inside keeps its gutter (its negative margins fall inside the padding), so a screen drawn in `#ws-root` by its script and one whose columns the server prints get the same 12px edge. `#ws-root` has no padding of its own; its `.pg-toolbar` and a server-drawn `#button_bar` stretch over the padding with `-.75rem` side margins.
- A new Workspace screen only needs `'extra classes' => 'workspace workspace_<name>'` and `<main id="content" class="container-fluid p-0"><div class="ws-shell"><div class="ws-shell-main">`; `tests/workspace_screen_frame_test.php` checks every `workspace_*.php` that draws `#content`.

## Why no list of screens?
- Until 2026-11-04 the padding sat on `#ws-root`, and screens without it (settings, MCP, template, calendar subscription, tables) were named in `:is(...)` / `:not(...)` lists. Each screen left out ran its cards against the page edge with the columns touching (MCP and template before #2882, tables 2026-11-01, calendar subscription reported by the owner). The E-mails list (`workspace_mail.php`) sat outside the shell with no `workspace` body class; it now uses the shell too.

## Where do sticky columns stop?
- `.ws-claude-status` (Claude, Pinegrap AI and voice cards) and `.ws-tl-side` (Decision Timeline side card) use `top: calc(var(--pg-navbar-h) + 1rem)`, like `.pg-settings-nav`. With `top: 1rem` they slid under the fixed 50px panel header when the window scrolled.

## How does the MCP servers card fit a phone?
- `#ws-mcp > .card-header` and its server rows (`#ws-mcp > .card-body > .ws-dept-row`) wrap; the name column has `flex-basis: 12rem`, so the buttons drop below it. Plain `.ws-dept-row` (departments, mailboxes, tool lists) still does not wrap. Without this a 390px screen squeezed the heading and the server name to 0px wide and pushed Remove off screen (page scrolled sideways).
