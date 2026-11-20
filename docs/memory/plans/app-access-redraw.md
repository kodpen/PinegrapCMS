---
name: app-access-redraw
description: Plan (owner 2026-11-20, urgent): Application Access redrawn: starting points, a permission picker with bulk choices, a guide per way to connect, an agent made from the application; AA1 to AA8
priority: 8
---

## What is asked?
- Owner (2026-11-20, in the architect's window, urgent; his words in `workflow/decisions-made-product-88.md`): the New application and the edit screens drawn again; choices told with icons and descriptions; ready starting points; one button each for all, none, all read, all write; the permission area no longer "a pile of buttons".
- Also: how MCP, OAuth, the mobile app and a plain connection are used told plainly, Claude Console too; an agent given to a channel from the application's screen, with its instructions.
- What went wrong for him: Claude Console asked for a token; connected over MCP it entered as the person, not as the agent `@console`.

## What is there today?
- `api_settings.php` (3,251 lines): a list, a small New Application dialog (name, three kinds as radio rows, the "Automation platform" start, every permission as a Read / Write button row) and a 520px drawer per application (`api/application-access-keys.md`, `application-access-automation.md`, `ui/look-settings-pages-outside.md`).
- Nothing says who a connection acts as: an OAuth connection is the person who signed in; a key is the application.
- A key is HTTP Basic only: a client that asks for one token cannot use it. The MCP address takes a key as it takes a sign-in (`api/api-mcp-server.md`).
- An agent in a channel takes three screens: the key here, the agent in Site Settings › Assistants and agents, the agent's row in the channel's settings.

## What did the architect decide? (2026-11-20)
- Application Access stays its own screen beside Connections in the same tool group: Connections holds the services the site reaches, this screen who may reach the site; one screen for both would mix two directions and two sets of rights. Each links the other.
- Starting points are a registry; nothing about the choice is stored: an application stays a server, device or OAuth row. Posted field names and the save stay, so the keys, the grace day and the locked rows keep their rules.
- A key also works as one token (`Authorization: Bearer`), the same secret in another shape, wherever the key is taken.
- The agent's row and its channel rows are written by the functions the Agents card and "Agents in this channel" use (`ws_agents_settings_check()`, `ws_agents_settings_write()`, `ws_channel_agent_save()`): no second rule, the same rights (an agent is added by role 0).
- "All write" ticks what moves money too, since the person asked for all: the picker marks those rows and counts them beside the button.
- Claude Console is named in its guide at the owner's word; what its form asks is read on Anthropic's pages first (AA7), never guessed.

## What binds it?
- No table and no column. No platform name on the automation starting point (owner, poll 121, `workflow/decisions-made-product-70.md`).
- One OAuth app a site (`api/application-access-keys.md`): the sign-in starting point opens it when it exists.
- New parts by look A's recipes (`ui/look-new-parts.md`); every text through `lang()` and `php tools/tr_add.php`.

## What is built?
- Features: `app-access-redraw-features.md`, `-features-2.md` (U1 to U14). Steps: `app-access-redraw-steps.md`. Claim as `[plan:app-access-redraw] <step> — <topic>`.

## What is left out?
- The screen inside Connections, permissions by single route, a key that expires by date, more than one OAuth app. Each is a later yes.

## Whose look?
- No pictures (`workflow/look-pictures-none.md`): built by the recipes, seen on dev; the owner sees it there.
