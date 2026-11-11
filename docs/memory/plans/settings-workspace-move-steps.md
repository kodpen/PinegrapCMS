---
name: settings-workspace-move-steps
description: Steps of plans/settings-workspace-move.md: SD1-SD2 the drawer inside the Site Settings dialog, SW1 the Workspace heading and its gate, SW2-SW6 the cards by category, SW7 the button and the old screen
---

## Which steps are there?
- S under 400 product lines, M about 1,000; no schema; each step its tests and its memory topic. They are built in the order written: each waits for the one before it. A step near 1,000 lines may be cut (SW3a, SW3b), each part landing whole cards.

- SD1 (M) The drawer, tried with Cron Jobs. A panel inside the Site Settings `<dialog>` that slides in from its right edge over the pane: a head (title, Close) and a body that scrolls on its own, long lines wrapped or scrolled inside it; never a second dialog in the top layer. The pane under it keeps its values and its unsaved mark; Escape and Close shut the drawer first, and focus returns to the button that opened it; on a phone it takes the dialog's width.
  - A card hands its drawer over as it hands a dialog over (`$pg_settings_modals[]`, `ui/settings-modal-card-dialogs.md`); the page path (`settings_<key>.php`) opens the same content.
  - First user: the Cron Jobs examples of `includes/settings/maintenance.php` (read-only command fields). Topic `ui/settings-modal-drawer.md`.
- SW1 (M) The Workspace heading and its gate, with the Defaults card as the proof. The heading and three category keys in `includes/settings/registry.php`, each with `<key>.php`, `<key>.save.php`, `settings_<key>.php`, the menu `case` and a help row (`ui/settings-modal-architecture.md`).
  - The gate (the pane's endpoint, the save path, the menu): a holder of the Workspace settings right opens these three and nothing else; a manager without it is refused them; every other category stays the manager's. Tests pin the three cases.
- SD1b (S, the owner's look on dev) The owner looks at SD1's drawer on dev and says whether it suits; the architect asks him and writes the answer here. The steps below wait for it.
- SD2 (S) The other card dialogs of the Site Settings dialog move into the drawer: the second dialog of `maintenance.php`, the Connect forms of `commerce_merchant.php` and `privacy_analytics.php`, every other `$pg_settings_modals[]` entry. The second-dialog path goes once nothing uses it.
- SW2 (M) Team and working hours, the rest: Departments (add, edit, archive), Team members, Who is in the team, Holidays and calendar feeds (`ws_holidays_settings_card()`, its dialogs into the drawer).
- SW3 (M) Claude (`ws_claude_settings_card()`: settings, test, key create and reset, its dialogs into the drawer) and Bulk changes.
- SW4 (M) Agents (save, delete, test; the request rows' cancel, retry, knock) and Pinegrap AI (settings, test, key).
- SW5 (M) MCP servers (`ws_mcp_settings_card()`: eleven actions, the catalogue, a person's own connections, the links of `workspace_mcp.php`).
- SW6 (M) Channels, mail and voice: Channel templates (archive, the links of `workspace_template.php`), Mailboxes (save, test, the .eml upload in the drawer), Voice transcripts, GitHub links.
- SW7 (S) The button and the old screen. "Workspace Settings" in the Workspace menu and screen (`includes/workspace/nav.php`, `screen.php`) opens the first Workspace category the reader may open. Every link to `workspace_settings.php` is repointed with `pg_settings_link()`: the Workspace cards, `workspace_mcp.php`, `workspace_template.php`, `includes/designer_ai.php`, the system status rows, the dashboard's AI card, `pg_settings_integration_links()`.
  - `workspace_settings.php` stays as an address that redirects, its anchors mapped to the cards' new places; a POST to it is refused. Help rows follow (`ui/panel-help-links.md`).
  - Close-out: the lasting rules to `ui/settings-modal-architecture.md` and a `workspace/workspace-settings-overview.md`; the plan's two files are deleted.

## Which order holds, and why?
- SD1 before the frame: a card with a form of its own needs the drawer. SW1 before the owner's look, so that the frame is not held by it.
