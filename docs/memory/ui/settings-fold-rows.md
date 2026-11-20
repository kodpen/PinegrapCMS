---
name: settings-fold-rows
description: Folded rows of a Site Settings pane (Assistants and agents, AT1): pg_settings_fold(), each part's state line, what opens a row, the cards kept whole inside the rows
---

## What is a folded row?
- A part of a settings pane drawn as one row: icon tile, name, one line of state, a state pill, a chevron; its card opens under it. One row is open at a time. First user: Site Settings › Assistants and agents (plan `assistants-settings-tidy`, step AT1).
- Helper: `pg_settings_fold($rows)` in `includes/settings/fold.php`; a row is key, name, icon, tone, line, pill, attention, html. Row ids start with `pg-fold-`, never `pgset-`: a `pgset-` id ends the card before it in the search index (`pg_settings_card_labels()`).
- Style: the "Folded rows of a settings pane" block of `backend.src.css`, after `.pg-set-drawer`. Inside a row a card loses its frame and its heading; a heading holding a button or link (Add an agent, Add a server) keeps the button.

## What stays as it was?
- Every card is still drawn by its own function (`ws_claude_settings_card()` and the rest) with its id (`pgset-ws-claude`…), fields, button names and values, and drawers. The save module, the registry, the anchors and the search index read them unchanged (`workspace/workspace-settings-row-names.md`).

## Which row opens?
- The pane arrives with the first part that needs attention open, else the first row: `pg_settings_fold_pick()`.
- On the client (`backend.src.js`): a click on a head; `pgSettingsFoldReveal(el)` opens the row an element sits in. `jump()` calls it (an anchor `#settings/<pane>/<card>` or a search hit), so do `refused()` (the first field a save marked), `reopen()` (a drawer a save opens again), `initSettingsAnchors()` and the page rail.
- After a save `spot()` notes the open row and `paint()` opens it again; `pgSettingsFoldPick(keys, asks)` takes the first asked row of the pane. Test: `tests/settings_assistants_fold_test.php`.

## What does each row of Assistants and agents say?
- Order: Claude, Pinegrap AI, Workspace agents, MCP servers, Voice, Canvas templates, Bulk changes (`ws_assistants_fold_order()`, `includes/workspace/assistants_fold.php`).
- Claude: "Ready · last run 14:05" (the date too when not today), "no run yet", the first missing sentence (pill Not ready, attention), or "Switched off" (pill Off). Pinegrap AI and Voice: the same three states with their own ready and off lines.
- Workspace agents: "3 agents · 1 not seen yet", pill "N waiting" (no attention: a waiting request is work, not a fault). MCP servers: "2 servers · 1 asks for a sign-in", pill Sign-in needed or Cannot be reached (attention), Ready when all are. Canvas templates: how many. Bulk changes: whether bulk deletes may be proposed.

## What does Claude's opened row hold? (AT2, #10860)
- The switch with one line of help, the application, the routine's address and token, the daily summary, then Try the connection beside one quiet button that opens the drawer "Set up on claude.ai" (`#pgset-ws-claude-setup`); it reads "Finish the set-up" while `ws_claude_missing()` is not empty (`ws_claude_setup_button()`).
- The drawer (`ws_settings_claude_setup_drawer()`, added to `$pg_settings_modals` by `workspace_assistants.php`) holds the steps of `ws_claude_setup_guide()` as Bootstrap's `.stepper.pg-stepper-cards` (`ui/step-rail.md`), then a note on what a run costs and who may ask. Before, the guide was an open `<details>` "How to connect it" in the pane. Test: `tests/settings_claude_setup_drawer_test.php`.

Related: docs/memory/ui/settings-modal-overview.md, docs/memory/ui/settings-modal-save-place.md, docs/memory/plans/assistants-settings-tidy.md
