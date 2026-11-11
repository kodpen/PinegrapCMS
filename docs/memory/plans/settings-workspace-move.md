---
name: settings-workspace-move
description: Plan (owner 2026-11-11): the Workspace settings move into the Site Settings dialog, a fifth heading with three categories, one card at a time; a card's dialog becomes a drawer in it; steps SD1-SW7
---

## What is it for?
- Owner (2026-11-11): "workspace ayarları çok karıştı, site ayarları altına taşıyalım. dikkatli olunması için adım adım. workspace ayarları düğmesi tıklandığında ilgili ayarlara gitsin." The Workspace Settings screen (`workspace_settings.php`) holds 14 cards and about 45 form actions on one page, apart from the Site Settings dialog (`ui/settings-modal-architecture.md`).
- The same day he reported that a dialog opened from a card inside the Site Settings dialog (Cron Jobs) cannot be scrolled and its lines run out of it. A second dialog layer is ruled out ("ayarlar kaydetmeden terkedilme riski var"): a drawer from the dialog's right edge is tried.
- The decisions: `workflow/decisions-made-workspace-13.md` (the place), `workflow/decisions-made-panel-3.md` (the drawer).

## Where do the cards go?
- A fifth heading "Workspace" after "Running the site", with three categories (owner: "Ayrı başlık, 3 kategori"):
  - Team and working hours: Departments, Team members, Who is in the team, Defaults, Holidays and calendar feeds.
  - Assistants and agents: Claude, Agents, Pinegrap AI, MCP servers, Bulk changes.
  - Channels, mail and voice: Channel templates, Mailboxes, Voice transcripts, GitHub links.
- The "Workspace Settings" button opens the first of the three the reader may open.

## What does every step keep?
- Who may change a Workspace setting: the Workspace settings right (`ws_rights()['settings']`), not the manager role. A role 3 holder of `manage_workspace_settings` opens the dialog and sees the Workspace heading alone; a manager kept out of the team sees no Workspace category; every save asks the right again. Tests pin all three.
- No schema. Where a value is kept, its checks and its messages stay (`workspace/workspace-settings-card-saves.md`, `workspace-settings-row-names.md`).
- A card moves once and whole, never drawn in two places. Until the last step the old screen keeps the cards not yet moved and, for a moved one, a line with a link to its new place.
- A moved card follows the dialog's contract, no second one (`ui/settings-card-save-rules.md`, `settings-card-own-button.md`): no form of its own in the pane; plain fields go with the pane's Save; a row action or a test is a named submit button that runs after the save; row fields are not `required` and are checked on the server; field names are unique in the pane. A form that must stand alone (a file upload, a long add or edit form) opens in the drawer.
- A card is simplified as it moves (owner 2026-11-11, #8750, `workflow/decisions-made-workspace-15.md`): what sets the thing up shows, rarely needed options sit under a closed "Advanced" part, a form shows only the chosen kind's fields. Nothing is dropped; the PR names what went under Advanced.
  - SW4's "Add an agent" form: Kind, then an application's handle, name, application and door (knock address and token only for a push door), or a model's handle, name, provider, model and key (address only for Azure or custom). Advanced: status, wake interval, wake note, monthly token limit.
- The save code is the old screen's `case` body moved into a function both callers use, not a copy.
- `workspace_settings.php` and its anchors (`#ws-mail`, `#ws-departments`) keep answering, in the manner of `pg_settings_legacy_anchors()`.
- Looked at in a browser (the sandbox) at desktop and phone width, light and dark, before a step is marked ready; the PR gives the reason: a unit test cannot reach the dialog's layout.

## Who holds it, where does it start?
- Coordinators hand the steps out; claim as `[plan:settings-workspace-move] <step> — <topic>`. The steps: `settings-workspace-move-steps.md`.
