---
name: workspace-screen-frame
description: Which Workspace screens pad their own main area, where sticky columns stop, how the MCP servers card wraps on a phone
---

## Which screens frame themselves?
- Screens whose row sits in `#ws-root` get their side padding from it, and the gutter-0 rule in backend.src.css drops the row's gutter. Settings, MCP connections (`workspace_mcp.php`) and channel templates (`workspace_template.php`) put their `.row` straight into `.ws-shell-main`: they pad it (`0 .75rem .75rem`), stretch `#button_bar` over that padding and stay out of the gutter-0 rule.
- Before PR "Workspace screens: MCP and template screens framed" the MCP and template cards ran edge to edge, with no gap between the columns. A new screen of that shape joins the same `:is(...)` / `:not(...)` lists.

## Where do sticky columns stop?
- `.ws-claude-status` (Claude, Pinegrap AI and voice cards) and `.ws-tl-side` (Decision Timeline side card) use `top: calc(var(--pg-navbar-h) + 1rem)`, like `.pg-settings-nav`. With `top: 1rem` they slid under the fixed 50px panel header when the window scrolled.

## How does the MCP servers card fit a phone?
- `#ws-mcp > .card-header` and its server rows (`#ws-mcp > .card-body > .ws-dept-row`) wrap; the name column has `flex-basis: 12rem`, so the buttons drop below it. Plain `.ws-dept-row` (departments, mailboxes, tool lists) still does not wrap. Without this a 390px screen squeezed the heading and the server name to 0px wide and pushed Remove off screen (page scrolled sideways).
