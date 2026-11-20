---
name: agent-guide-panel
description: The guide's panel topic for a browser agent: screens from the Ctrl+K list under the viewer's gates, Save button and Visual Page Editor rules, whose gates, sizes
module: agent_guide_panel, panel_screens
---

## What does the panel topic say?
- `includes/fn/agent_guide_panel.php` (#10725, plan ai-guide AG5), four sections. `screens`: each screen the viewer may open as "English label (address)", one line per main-menu section (Pages, Files, Store, Visitors, Contacts, Users, Email, Calendars, Forms, Menus, Ads, Design); a screen not listed answers Access denied.
- `finding`: Ctrl+K; for managers the Site Settings dialog and System tools; the `?` shortcuts. `saving`: only the Save button keeps a change. `designer`: the Assistant box or `design_pages_get` then `design_proposals_create`, never drags on the canvas.

## Where do the screens come from?
- From the panel search box's own list, `pg_panel_search_screens()` in `includes/fn/panel_screens.php`, under its gates (`pg_panel_search_viewer_gates()`): a screen is in the topic exactly when the viewer gets its Ctrl+K chip. A new chip in that list reaches the guide with no change here; a chip whose address is a `#` fragment (a header dialog) is left out.
- The labels are the list's English `lang()` keys, so the guide stays English whatever the panel's language.

## Whose gates?
- `pg_agent_guide_panel_viewer()`: the owner of the API or MCP connection (`api_current_app()['owner']['id']`), else the panel's signed-in `USER_ID`; their `user` row (enabled only) is mapped by `pg_agent_guide_panel_user()` to the rights validate_user() gives. Read once per request (`pg_agent_guide_panel_gate()`, which the tests also use to set a viewer). No viewer: no screens, the rules stay.
- The topic names no tool prefixes, so it is listed to every caller; the text, and so the digest, differs per viewer.

## How big is it?
- Measured at #10725: 3,122 bytes for an administrator with every module on, 2,624 for a manager, 1,101 for a role 3 user with an edit folder (limit 4,096, `agent-guide-engine.md`). `tests/agent_guide_panel_test.php` holds the administrator's size.
