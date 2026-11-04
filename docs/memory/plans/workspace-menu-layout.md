---
name: workspace-menu-layout
description: Plan (owner decision 2026-10-30) for a tidier Workspace list in the main menu: search, places, one Channels section, discussions, Tools tiles; channel screen unchanged
---

## What is this plan?
- Owner request (2026-10-30, "Menü çok karışık"): the Workspace list got the layout the owner approved (`workflow/decisions-made-workspace-5.md`), described in `workspace/workspace-menu-list.md`; the channel screen as redrawn: `workspace/workspace-channel-screen.md`.
- Kept: the place (#2545), the data (`ws_side_payload()`: every entry under its screen's gate, the script hides nothing), `<a href>` links, pin, drag into a group, right-click menus, the closed state in localStorage, the panel colours (`--pg-*`, `--ws-accent`) in light and dark.
- Files: `assets/js/workspace_side.js` (`draw()`), `includes/workspace/nav.php`, the Workspace block of `assets/css/backend.src.css`, `includes/workspace/tour.php`, `tests/workspace_side_test.php`. No schema, no new right.

## Who builds it?
- From M2 on, only a session the owner names in chat for the design; a coordinator does not hand the steps out and a worker does not claim them (owner, 2026-10-31, `workflow/decisions-made-workspace-5.md`).
- Each step is checked on dev against the drawn design at desktop and phone width, light and dark, before it is marked ready.

## What are the steps?
- Done: M1 #3794, M2 #3817, M3 #3817, M4 #6281

## What is still open?
1. My Tasks count: none for now; a count of overdue and today's tasks on its tile needs one more query in `ws_side_payload()`.
2. The bell's rows keep their old look (two full-width buttons under each row); a compact row, the title as the link, would be a step of its own.

## Where to start?
- M1 to M4 are done; what is left is under What is still open?, each a small step of its own. Once both are settled the plan goes, its lasting rules being in `workspace/workspace-menu-list.md`, `workspace/workspace-menu-tools.md` and `ui/notifications-one-inbox.md`.
