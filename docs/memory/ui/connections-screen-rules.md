---
name: connections-screen-rules
description: What the Connections screen and the registry keep: one place per setting, no state table or outside call, where it lives, its names, what is left out, how a setting is merged
module: connections_screen
---

## What does the screen never do?
- It is no second home for a field: a row's drawer shows the state, the account, what uses it and the last error, and opens the setting's one place. No field of a setting is shown or saved on it (owner 2026-10-30, `workflow/decisions-made-product-14.md`).
- No table or column for a state, and no history of states: a state nothing stored can tell is left out and named (`no_state`). A need for one is a question to the architect.
- No outside call while the list is drawn: opening the screen leaves a stored account as it was.
- No copy of Application Access: the other side is one block with its counts and one button to it.
- No catalogue of add-ons to install (`plans/addon-hook-system.md`). No one-click connect through a broker: it waits for the owner's decision on Google's verification (`plans/reverse-oauth-leftovers.md`).

## Where does it live, and what is it called?
- Architect (2026-11-17, agent channel message 7524): System tools › Connections, as the redraw of the Connected accounts screen, not a list inside the Site Settings window. The panel-old-screens plan does not draw it a second time.
- Owner (2026-11-17, agent channel poll 120): the screen is "Connections" ("Bağlantılar") wherever it is named; the provider's accounts section in it keeps "Connected accounts" (`workflow/decisions-made-product-70.md`).
- The screen is for a manager; each row, tool and card stays under its own gate (`ui/settings-system-tools.md`, `connections-screen-layout.md`).

## What does a change to it keep?
- A new outside service is a row of the registry (`api/connections-registry.md`), never a row typed on the screen. The public figure is the registry's count, kept by a test and never typed in a text.
- The accounts' rules: tokens sealed, a refresh token never read, a provider or a purpose added by a file (`api/api-connected-accounts.md`).
- Look A's recipes; texts through `lang()`; a pure function of `includes/fn/connections_screen.php` gets its test first.
- A change of the screen's look is drawn at 1440, 1024 and 390, light and dark, with the longest Turkish names, and shown to the architect beside the screen as it is.

## How is a scattered setting merged into one place?
- Each merge that moves a setting (GitHub's five places, the AI keys' six, mail's four, a provider's client, the names) is its own question to the owner with a sheet, then a step of `plans/connections-merges.md`. A card's address and its old anchors stay when it moves (`pg_settings_legacy_anchors()`).

Related: docs/memory/ui/connections-screen-layout.md, docs/memory/ui/connections-screen-drawers.md, docs/memory/api/connections-registry.md, docs/memory/plans/integrations-census-screens.md
