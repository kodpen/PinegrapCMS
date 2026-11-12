---
name: workspace-settings-overview
description: Start file for the Workspace settings: the three categories of the Site Settings dialog, who opens them, the rules every card keeps, the old workspace_settings.php address, which file holds what
---

## Where are the Workspace settings?
- In the Site Settings dialog, a fifth heading "Workspace" after Running the site, three categories (owner 2026-11-11, `workflow/decisions-made-workspace-13.md`): Team and working hours (`workspace_team`), Assistants and agents (`workspace_assistants`), Channels, mail and voice (`workspace_channels`). Gate and placement: `ui/settings-workspace-gate.md`.
- The "Workspace Settings" entry of the Workspace menu (`ws_nav_groups()`), the screen config's `urls.settings` and every link that pointed at the old screen use `pg_settings_workspace_link($anchor, $user = null)` (`includes/fn/output.php`, PR #8931).

## What does pg_settings_workspace_link() answer?
- An old anchor (`ws-mail`, `ws-mcp-4`: the longest moved anchor it starts with) or a card's own (`pgset-ws-mail`) gives that card's category and card: `settings_workspace_channels.php#pgset-ws-mail`. Anything else gives the first Workspace category: `settings_workspace_team.php`.
- It reads `pg_settings_legacy_anchors()`, never the database, so a dashboard card or a status row may call it. With `$user` it keeps only the categories that reader may open and answers '' when none.
- The dialog's script takes `ws-` anchors on a `settings_<key>.php` address too (`fromUrl()` in `backend.src.js`), so `urls.settings + '#ws-voice'` in workspace.js opens the Voice card.

## What is left of workspace_settings.php?
- An address (PR #8931): the old gate (`ws_screen_gate()`, "access denied to the workspace settings" logged), then a redirect to the first Workspace category the reader may open. The browser carries an old `#ws-…` fragment over it and `fromHash()` opens the dialog on that card. `?mcp_connected=<id>` goes on to Assistants and agents.
- A POST is refused with 405 and "The Workspace settings are in the Site Settings now. Nothing was saved."; nothing reads `$_POST` there. `ws_side_screen()` still lights Workspace Settings for it and for the three `settings_workspace_*.php` pages.
- The card functions still carry the old screen's own forms (their `$form === null` branch, `$self_url`); nothing draws them now. `tests/workspace_settings_lists_test.php` keeps their list names in its allow-list until they are removed.

## What does every Workspace card keep?
- Who may change it: the Workspace settings right (`ws_rights()['settings']`), not the manager role; every save asks again. No schema came with the move.
- The dialog's contract (`ui/settings-card-save-rules.md`, `ui/settings-card-own-button.md`): no form of its own in the pane; plain fields go with the pane's Save; a row action or a test is a named submit button run after the save; row fields are not `required` and are checked on the server; field names unique in the pane; a form that must stand alone opens in the drawer (`ui/settings-modal-drawer.md`).
- The save is one function the save module calls, never a copy; a list field goes into the category's `lists` entry in `registry.php`.
- Rarely needed options sit under a closed "Advanced" part; a form shows only the chosen kind's fields (owner 2026-11-11, `workflow/decisions-made-workspace-15.md`).

## Which file holds which card?
- Team and working hours: [[workspace-team-settings]] (Departments, Team members, Defaults in `ui/settings-workspace-gate.md`), [[workspace-holiday-settings]].
- Assistants and agents: [[workspace-assistant-settings]] (Claude, Bulk changes, Pinegrap AI), [[workspace-agent-settings]], [[workspace-mcp-settings-dialog]].
- Channels, mail and voice: [[workspace-channel-settings]] (templates, voice, GitHub), [[workspace-mail-settings-dialog]].
- Saves and names: [[workspace-settings-card-saves]], [[workspace-settings-row-names]].

Tests: `tests/settings_workspace_link_test.php`, `tests/settings_workspace_gate_test.php`.
