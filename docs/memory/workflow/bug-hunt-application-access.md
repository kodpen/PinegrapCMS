---
name: bug-hunt-application-access
description: Bug hunt of Application Access (api_settings.php and its includes/api files), architect 2026-11-26 after #11805: the six faults fixed in #11826, no issue opened, what read clean
---

## What was the area?
- System tools › Application Access (`api_settings.php`) and what it draws with: `includes/api/keys.php`, `app_access.php`, `app_templates.php`, `app_new_view.php`, `app_permissions_view.php`, `app_drawer_view.php`, `app_agent_view.php`, `assets/js/api_app_access.js`, `includes/workspace/agent_app_setup.php`, the allowed origins card (`includes/api/cors.php`). Named by the architect on 2026-11-26.
- Not repeated: the agent guide and the Refused calls card (`bug-hunt-areas-agent-guide.md`), the Claude page row (`bug-hunt-areas-connections-merges.md`).

## What did it fix? (PR #11826 names each with file and line)
- Logs tab: "today" (time alone) was told in the site's zone while the time was printed in the person's own zone; a call from late yesterday their time read as today's. `api_app_log_time_type()` takes the label's zone.
- AI agent tab: Return in a field posted the drawer's default (`api_action=update`): "The application was saved." and no agent. Return now presses the tab's own button.
- New application, "Agent in a channel": Agent name was copied from Application name when the card was picked, before that name was typed, so Create refused the agent for a missing name. It now follows Application name until typed in (cut to 100).
- Allowed browser origins: a refused list was replaced in the box by the saved one; the box now shows what was typed (session, read once).
- Permission picker: "{n} permissions that move money are ticked." stayed when another application was opened or another starting point ticked its rows (`clearPickNote()`).
- AI agent tab: a refused save (a handle taken, a bad push address) opened the tab again with the saved agent's values; it now shows what was typed (`api_app_agent_typed()`, session `api_agent_typed`).

## What was opened as an issue?
- None.

## What read clean?
- Rows only through `api_app_create()`; a secret only for a server row; the plain secret and token handed through the session once; a failed INSERT says so.
- New secret: `api_app_rotate_secret()` with 86400, keyless rows refused on the screen and in the UPDATE; a second rotation inside the day replaces the previous secret (one previous is kept by design).
- Rank: one check before every branch naming an `app_id` (update, agent_setup, rotate, delete, webhooks, devices); a deleted owner unlocks; the drawer, the event card and the agent tab hide their buttons for a locked row.
- One OAuth app: refused before `api_app_create()`; the sign-in card opens the served one.
- Starting points: the kind read again from the posted card; the ticks of each card equal what step 2 posts; no card ticks a money row (the Automation and Reporting cards checked against the schema).
- Bulk buttons and money counts: PHP and script twins agree; search narrows them.
- Allowed origins: role 0 and 1 only, refused whole, unchanged writes nothing.
- The drawer's Return in other tabs: a hidden first button posts Save, never New secret or Delete.
- Dates: created, device "since" and "Registered on" in the site's order; "Expires on" is a native date field posting ISO.

Related: docs/memory/workflow/bug-hunt.md, docs/memory/api/application-access-overview.md
