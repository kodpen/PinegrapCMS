---
name: workspace-menu-layout
description: Plan (owner decision 2026-10-30) for a tidier Workspace list in the main menu: search, places, one Channels section, discussions, Tools tiles; channel screen unchanged
---

## What is this plan?
- Owner request (2026-10-30, "Menü çok karışık"): the Workspace list (`workspace/workspace-menu-list.md`) gets the layout below, which the owner approved (`workflow/decisions-made-workspace-5.md`); the channel screen is redrawn in `plans/workspace-channel-layout.md`.
- Kept: the place (#2545), the data (`ws_side_payload()`: every entry under its screen's gate, the script hides nothing), `<a href>` links, pin, drag into a group, right-click menus, the closed state in localStorage, the panel colours (`--pg-*`, `--ws-accent`) in light and dark.
- Files: `assets/js/workspace_side.js` (`draw()`), `includes/workspace/nav.php`, the Workspace block of `assets/css/backend.src.css`, `includes/workspace/tour.php`, `tests/workspace_side_test.php`; new words through `tools/tr_add.php`. No schema, no new right.

## Who builds it?
- From M2 on, only a session the owner names in chat for the design; a coordinator does not hand the steps out and a worker does not claim them (owner, 2026-10-31, `workflow/decisions-made-workspace-5.md`).
- Each step is checked on dev against the drawn design at desktop and phone width, light and dark, before it is marked ready.

## What is the order, top to bottom?
1. Head: one search field that opens the workspace search (`hooks.palette`, elsewhere `?view=home&palette=1`; it finds channels, people, messages, decisions, tasks, notes and files), so the separate message search input goes. Beside it: "+" New channel, the gear of Workspace Settings (from `nav.foot`) and the tour button (kept in sight: the tour's `ready()` waits for `[data-ws-tour]`).
2. Places, no heading: Overview (keeps its name), Inbox (unread count), My Tasks (moved up from Work and planning).
3. Channels, one heading: pinned first (pin icon), then the groups (tree; a closed group shows its unread), then the person's other channels, then the channels not joined, dimmed (`ws-notjoined`). The Pinned, My channels and Other channels headings go; sorting, drag and drop-out keep working per part.
4. My discussions (today's Discussions, moved below the channels): title, `#channel` under it, unread count, Show all after the first few.
5. Tools: two-column tiles of a new `tools` group (Notes, Tables, Flows, Work Calendar, Planning Board, Decision Timeline, E-mails, each under its gate) and a last tile, More, opening a `more` group: Scheduled messages or actions, My MCP connections, Calendar subscription, Archived channels, Private channels (staff).
6. Counts: a mention count in the workspace accent, an unread count neutral; a closed section still shows the open entry and its unread sum.

## What are the steps?
- M1 Head, places, Channels, My discussions: done #3794 (`workspace/workspace-menu-list.md`; settings gear in a new `head` group, `foot` gone).

- M2 Tools tiles and More: `tools` and `more` groups replace `work` and `records` (`ws_nav_group_titles()`), tile CSS, tour steps inside More open it first (`PGWsSide.openSections(['more'])`), tests, memory. Also in M2: drop the `search` hook of `sideHooks()` in `workspace.js` (draw() no longer calls it), and the tour hook's `openSections(['work', 'records'])` follows the new group keys.

- M3 The bell is the one inbox: filters All, Site, Workspace (mentions, approvals, tasks, flows), repeated flow runs folded, a wider panel; the list's Inbox opens the bell on Workspace and its own count goes. `ws_inbox` stays the store (API, sync, push) and keeps its bell rows (`ws_bell_add()`); no schema.

## What is still open?
1. My Tasks count: none for now; a count of overdue and today's tasks needs one more query in `ws_side_payload()`.

## Where to start?
- M2. Check M1 and M2 on dev in light and dark, with the rail narrowed and on a phone (Channels pill, `PGWsSide.reveal()`), on the channel screen (hooks) and on another panel page (links).
