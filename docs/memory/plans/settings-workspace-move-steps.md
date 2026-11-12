---
name: settings-workspace-move-steps
description: Steps of plans/settings-workspace-move.md: SD1-SD2 the drawer inside the Site Settings dialog, SW1 the Workspace heading and its gate, SW2-SW6 the cards by category, SW7 the button and the old screen
---

## Which steps are there?
- S under 400 product lines, M about 1,000; no schema; each step its tests and its memory topic. They are built in the order written: each waits for the one before it. A step near 1,000 lines may be cut (SW3a, SW3b), each part landing whole cards.

- SD1 (M) The drawer, tried with Cron Jobs. Done (#8658): `ui/settings-modal-drawer.md`.
- SW1 (M) The Workspace heading and its gate, with the Defaults card as the proof. Done (#8675, `ui/settings-workspace-gate.md`): three categories under a Workspace heading, opened by the Workspace settings right alone.
- SD1b The owner's look on dev. Done (2026-11-11, #8677): "Yeniledim, açılıyor", no change asked (`workflow/decisions-made-panel-3.md`).
- SD2 (S) The other card dialogs of the Site Settings dialog move into the drawer. Done (#8710, `ui/settings-modal-card-extras.md`): none was left (Cron Jobs was SD1; the Connect entries are hidden forms, kept beside the pane); the second-dialog path is gone.
- SW2 (M) Team and working hours, the rest, cut in two. Done (#8721, #8741):
  - SW2a Departments (add, edit, archive), Team members, Who is in the team. Done (#8721): `ui/settings-drawer-card-form.md`, `workspace/workspace-settings-card-saves.md`.
  - SW2b (S) Holidays and calendar feeds. Done (#8741): `workspace/workspace-holiday-settings.md`; the category's link card went with it.
- SW3 (M) Claude (`ws_claude_settings_card()`: settings, test, key create and reset, its dialogs into the drawer) and Bulk changes. Done (#8801): `workspace/workspace-assistant-settings.md`.
- SW4 (M) Agents and Pinegrap AI, cut in two. Done (#8814, #8832):
  - SW4a Agents (save, delete, test; the request rows' cancel, retry, knock). Done (#8814): `workspace/workspace-agent-settings.md`.
  - SW4b Pinegrap AI (settings, test, key). Done (#8832): `workspace/workspace-assistant-settings.md`.
- SW5 (M) MCP servers (`ws_mcp_settings_card()`: eleven actions, the catalogue, a person's own connections, the links of `workspace_mcp.php`). Done (#8844): `workspace/workspace-mcp-settings-dialog.md`; the Assistants and agents link card went with it.
- SW6 (M) Channels, mail and voice: Channel templates (archive, the links of `workspace_template.php`), Mailboxes (save, test, the .eml upload in the drawer), Voice transcripts, GitHub links.
  - SW6a Channel templates, Voice transcripts, GitHub links. Done (#8865): `workspace/workspace-channel-settings.md`.
  - SW6b Mailboxes (save, test, the .eml upload in the drawer). Done (#8899): `workspace/workspace-mail-settings-dialog.md`; the link card is gone. Next: SW7, open for any worker once #8899 lands.
- SW7 (S) The button and the old screen. "Workspace Settings" in the Workspace menu and screen (`includes/workspace/nav.php`, `screen.php`) opens the first Workspace category the reader may open. Every link to `workspace_settings.php` is repointed with `pg_settings_link()`: the Workspace cards, `workspace_mcp.php`, `workspace_template.php`, `includes/designer_ai.php`, the system status rows, the dashboard's AI card, `pg_settings_integration_links()`.
  - `workspace_settings.php` stays as an address that redirects, its anchors mapped to the cards' new places; a POST to it is refused. Help rows follow (`ui/panel-help-links.md`).
  - Close-out: the lasting rules to `ui/settings-modal-architecture.md` and a `workspace/workspace-settings-overview.md`; the plan's two files are deleted.

## Which order holds, and why?
- SD1 before the frame: a card with a form of its own needs the drawer. The owner's two findings on the settings screens are done apart from this order: the gap between cards (`ui/settings-card-chrome.md`, #8768) and a page left open across an update (`ui/settings-pane-stale-page.md`, #8787).
