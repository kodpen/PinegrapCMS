---
name: connections-hub-steps
description: Plan connections-hub, steps CN1 to CN7: the registry and its census, the sheet, the Connections screen, the drawers, the searches, the merges asked one by one, close-out; what every step keeps
---

## Which steps are there?
- CN1 (M) The registry and its census.
  - New: the list as pure data in a module of its own (named in its memory file's `module:`), its tests, memory `api/connections-registry.md` with every row's place and state reader and the services that show no state.
  - No screen changes. Cut as CN1a (shop and communication) and CN1b (the rest) when over 1,000 product lines, said before the claim.
  - Features: H1, H2, H3, H4, H5.

- CN2 (S, the sheet) The screen, drawn before it is built.
  - One sheet at 1440, 1024 and 390, light and dark, the longest Turkish names: the list with every state, the attention block, the drawer of a provider with its application and accounts, the drawer of a service whose fields live on a card, the other side's block, a search with no result.
  - Posted in the agent channel; the architect brings it to the owner as one question.

- CN3 (M) after CN1 and the yes to CN2: the screen.
  - Existing files: `connections.php` (595 lines, an old screen), `includes/settings/registry.php` (the tool's label, its search words).
  - The list, the search, the groups, the states, the attention block; what the screen does today in its new place.
  - Tests: the order (attention, connected, the rest) and the search as pure functions; a row hidden from a user who may not open its place.
  - Features: H6, H7, H9, H11.

- CN4 (M) after CN3: the drawers and the other side.
  - The drawer for each kind of row; the last error on file where one is stored; the block that leads to Application Access with its counts.
  - Features: H8, H10.

- CN5 (S) after CN1: the searches and the Integrations category.
  - Existing files: the Site Settings search index and `pg_panel_search_tool_actions()` (`ui/settings-modal-search.md`), `pg_settings_integration_links()`.
  - Features: H12.

- CN6 (M each, the owner's answer first) The merges, one at a time: GitHub, the AI keys, mail, a provider's client, the names. Each is a sheet and a question through the architect, then a step of its own with its `pg_settings_legacy_anchors()` rows.
  - Features: H13.

- CN7 (S) after CN4 and CN5: close-out. The lasting rules into `ui/` and `api/`, this plan's files deleted; the merges still open go to a plan of their own.

## What waits for what?
- Waiting for nothing: CN1 and CN2. CN3 waits for CN1 and the owner's yes to the sheet; CN4 for CN3; CN5 for CN1.
- CN3 and CN4 are not open together: both edit `connections.php`.

## What does every step keep?
- One place per setting: no field is shown or saved in a second place (owner 2026-10-30, `workflow/decisions-made-product-14.md`).
- No table and no column; a need for one comes back as a question to the architect. No outside call while the list is drawn.
- The accounts' rules: tokens sealed, a refresh token never read, a provider and a purpose added by a file (`api/api-connected-accounts.md`).
- The gates of today: the screen for a manager, each tool and card under its own gate (`ui/settings-system-tools.md`).
- Look A's recipes; texts through `lang()`, the Turkish with `php tools/tr_add.php`; a pure function gets its test first.
- A PR body carries the H numbers of its step with the test or picture that shows each, pictures at 1440, 1024 and 390 beside the sheet, and what could not be verified.
