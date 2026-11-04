---
name: workspace-screen-messages
description: Server-drawn Workspace screens show liveform errors, warnings and notices as corner toasts through ws_screen_messages(), never the panel's Notice/Warning/Error boxes (#6799)
---

## How does a Workspace screen show an operation's result?
- Through `ws_screen_messages($liveform)` (`includes/workspace/screen.php`): it reads the form's errors, warnings and notices and returns an inline script that calls `pgToast()` inside `pgUI.ready()`. Errors are `danger`, warnings `warning`, notices `success`. The screen still calls `remove_form()` once it is drawn.
- `ws_screen_toasts()` (pure) turns the stored escaped markup into plain text (pgToast escapes its message) and drops repeats. One link in a message (the "Open the draft flow" notice of `workspace_wtables.php`) is handed over as `link` {url, label} when it is a site path or http(s); `pgToast()` draws it after the text and the toast stays 10 s.
- Used by `workspace_wtables.php`, `workspace_mcp.php`, `workspace_calendar_feed.php`, `workspace_settings.php` and `workspace_template.php`.

## Why?
- Owner standard (#6799): the rest of the Workspace answers in the corner; the full-width "Notice: The table was made: add its columns." box on Tables, and the same box on the MCP, calendar feed, settings and template screens, looked like a different application. Same rule as the File Manager and the designer (`ui/app-screen-messages.md`).

## What keeps a new screen from printing a box again?
- `tests/workspace_screen_toasts_test.php` fails when any `workspace*.php` contains `output_errors()`, `output_notices()` or `get_warnings()`.
- A message tied to one field (the settings screen's refused fields, `ws_settings_refused_fields()`) stays next to that field.
