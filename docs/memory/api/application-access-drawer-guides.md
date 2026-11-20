---
name: application-access-drawer-guides
description: Application Access's edit drawer (head, tabs, Overview, Connect) and the connection guides of api_app_guides(), also shown in step 3 of New application; which guide suits which application
---

## What does the edit drawer show?
- `#api_drawer`, `min(760px, 100%)`, from the end; under 576 px the script swaps in `drawer-bottom` (88vh). Drawn by `api_app_drawer_view()` (`includes/api/app_drawer_view.php`) to `plans/app-access-redraw/app-edit.html`, since AA3. `openApp()` in `api_settings.php` fills it.
- Head: the kind's tile, the name, "Integration · key and secret · Acts as: the application itself" (OAuth and team devices: "sign-in", "the person who signs in"), the list's state as a pill, Close.
- Tabs (`pg-seg`, role=tab): Overview, Connect, Permissions (its count beside it), Events, Devices (Connections on OAuth), Logs. The Agent tab comes with AA5. The old Security tab is gone: its fields are Overview's Advanced row.

## What is on Overview?
- Four facts, the figure cards' compact form (`.pg-figures.pg-figures-sm`, Active `.is-good`, errors above 0 `.is-bad`): Status, Last used, Errors in 24 hours, Owner. Then Details, a card with the Active switch and the key, Summary (Permissions with Go to permissions; Advanced folded: IP list, end date, rate limit) and Danger at the foot: New secret (the old works a day) and Delete with its confirm.
- Foot: "For the services the site reaches: Connections" and Save. On Connect the foot is "API Documentation" and Close: Connect saves nothing.
- A locked row hides Save, New secret and Danger (`application-access-keys.md`).

## Which guides are there?
- `api_app_guides($at)` (pure; the site's addresses come in), in order: key_secret, token, mcp_signin, devices, agent. Each: icon, tone, title, line, acts (application or person), kinds, secret, steps (title, text, a value with Copy or a link), clients, notes.
- MCP with sign-in: the MCP file address, the compact list's (`mcp-compact-list.md`), Connect to Claude (`claude-connect-button.md`); no short or discovery address.
- One token: the API and the MCP address and `Authorization: Bearer TOKEN` (`application-access-keys.md`). Agent in a channel: PINEGRAP_KEY and PINEGRAP_SECRET, the start text (`ws_agent_start_text()`, English as written, only with the Workspace), how it waits.
- Id `console` is kept for the Claude Console guide (AA7b), after token.
- Guide texts go through `lang()`; the start text an agent reads stays English (`workflow/decisions-made-product-92.md`).

## Which guide suits which application?
- `api_app_guide_fit($guide, $kind, $has_secret)`: '' or `kind` / `no_secret`. On Connect every guide is a folded row (`pg_settings_fold_row()`); one that does not suit is greyed (`.api-guide-off`, readable ink, no opacity) and its first line inside says why; the first that suits opens.

## What is step 3 of New application?
- `api_app_connect_step()` turns what Create or a new secret handed over in the session (now with `start` and `token`) into step 3 inside `#api_new`, which opens by itself: a success line, "shown only now" when a secret is there, step 1 the values (site address, key, secret and token masked with an eye, each with Copy), then the starting point's guide (`api_app_guide_for()`), then "Connecting another way?" with the other guides that suit.
- The foot: "I have copied the secret" and Close; while a secret shows, the drawer's hide is refused until it is ticked. Closing removes step 3 from the page. A new secret shows step 3 without the step line.
- It replaced the three centred sheets.

## Which parts does it use?
- Architect (2026-11-20): no new facts recipe; `.pg-figures-sm` added in "Look A: figure cards". Steps: `.stepper.pg-stepper-cards` (`ui/step-rail.md`). Design examples draws both (DX2, DX3). A fold row in `.pg-card-inner` gets a top border.
- `includes/settings/fold.php` opens under `PG_API_PANEL` too.

Related: docs/memory/api/application-access-new-sheet.md, docs/memory/api/application-access-keys.md, docs/memory/ui/screen-composition-rules.md
