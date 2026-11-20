---
name: app-access-redraw-steps
description: Plan app-access-redraw, steps AA1 to AA8: the New application sheet, the permission picker, the drawer and guides, the key as one token, the Agent tab, Connections' row, Claude Console, close-out
---

## Which steps are there?
- AA1 (M) first: the starting points and the sheet.
  - New `includes/api/app_templates.php` (the registry, pure) and `includes/api/app_new_view.php` (the three steps' markup); `api_settings.php` only calls them in place of the New Application dialog and reads the posted starting point in its create branch. The automation start moves into the registry unchanged.
  - Tests, red first: the registry's shape, the automation card ticks what it ticks today, no starting point ticks a row that moves money, the sign-in card with and without an OAuth app. Features: U1 to U5 (step 3 shows today's sheet until AA3).
- AA2 (M) after AA1: the permission picker.
  - New `includes/api/app_permissions_view.php` (`api_settings_permission_rows()` moves there, drawn again; an icon and a line for every row of `api_scope_groups()`), new `assets/js/api_app_access.js` (search, counts, the bulk buttons; a plain file, no bundle), `api_app_bulk_choice()` in `includes/api/app_access.php`.
  - Tests: every scope group has its area, icon and line; the bulk rule on rows with read alone, write alone and both; the script twin against the PHP. Features: U6 to U8.
- AA3 (M) after AA2: the drawer and the guides. New `includes/api/app_guides.php` (the registry) and `includes/api/app_drawer_view.php` (head, tabs, Overview, Connect); `api_settings.php` loses the drawer's markup to it. The one-time sheet becomes step 3 with the guide. Features: U9 without the token, U14.
- AA4 (S) after AA1, beside AA2: the key as one token. `includes/api/auth.php` (the token read before a device token by its prefix), `includes/api/keys.php` (`api_app_token()`), the OpenAPI security text. Tests: accepted on the API and the MCP address, a wrong secret counted as a Basic one is, the grace day, never from the query string. The sheet's field comes with AA3. Feature: U10.
- AA5 (M) after AA3: the Agent tab and the agent starting point. New `includes/workspace/agent_app_setup.php` (`ws_agent_app_setup_check()`, `_write()`, the channel list a person may manage) and `includes/api/app_agent_view.php`; `api_settings.php` only the tab's call and one POST branch. Tests: the rights, each refusal names its part, nothing written twice on a second save. Tried on dev: an agent made from the sheet answers in its channel. Features: U11 to U13.
- AA6 (S) after AA3: Connections' row and the link back (`pg_connections_registry()` and its facts reader). Feature: the last line of U14.
- AA7 (M) after AA3 and AA4: Claude Console, as `app-access-redraw-console.md` says: the agent's own routes as MCP tools for its own key (the route flag and the tool list's reader), the second sending form in `includes/workspace/agent_instructions.php` and the start text, the guide in `app_guides.php`. Tests: the three callers that never see the tools; the instructions' version changes. Features: U15 to U18.
- AA8 (S) close-out, after all: every U number checked against the code with its PR; `api/application-access-overview.md` and its files written, `api/application-access-keys.md`, `application-access-automation.md` and `ui/look-settings-pages-outside.md` corrected; the user documentation's page noted in `workflow/user-docs-pages.md`; the `[NEW]` line named; this plan deleted.

## Which steps run side by side?
- `api_settings.php` is entered by AA1, AA3 and AA5 in that order, each after the one before has landed; AA2, AA4 and AA6 stay in their own files.
- AG7 of `plans/ai-guide.md` never enters `api_settings.php` or the guides: AA3 writes the compact address into `app_guides.php` once AG7 has landed, and names the one address until then.

## What does each step's PR carry?
- `Decision:` the owner's words in the architect's window, 2026-11-20 (`workflow/decisions-made-product-88.md`); no migration step; what was seen on dev where a step draws.
