---
name: settings-fold-rows
description: Folded rows of a Site Settings pane (Assistants and agents, AT1): pg_settings_fold(), each part's state line, what opens a row, the cards kept whole inside the rows
---

## What is a folded row?
- A part of a settings pane drawn as one row: icon tile, name, one line of state, a state pill, a chevron; its card opens under it. One row is open at a time. First user: Site Settings › Assistants and agents (AT1, #10807).
- Why: owner (2026-11-20) asked the tab gathered, its parts seen at a glance and Claude's long guide out of the pane (`workflow/decisions-made-panel-11.md`); he said yes to the drawing in poll 190 (`workflow/decisions-made-panel-12.md`).
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

## Where are the opened rows described?
- `ui/settings-claude-row.md`: its one column, the fold "Application and key" and its two drawers. Pinegrap AI's and Voice's: `ui/settings-ai-voice-rows.md`. The lists capped at five rows: `ui/settings-assistants-long-lists.md`; the help lines: `ui/settings-assistants-help-lines.md`.
- Every opened row keeps: one column, one line of help a field or part, no paragraph and no list of requests in the pane, a list of at most five rows then "Show all N", the end above the dialog's foot at its large size.

Related: docs/memory/ui/settings-modal-overview.md, docs/memory/ui/settings-modal-save-place.md, docs/memory/ui/settings-assistants-long-lists.md
