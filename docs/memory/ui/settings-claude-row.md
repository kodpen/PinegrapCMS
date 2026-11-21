---
name: settings-claude-row
description: Claude's opened row in Site Settings › Assistants and agents (AT2b): one column, the fold Application and key, the drawers Set up on claude.ai and The latest requests, what a test pins
---

## Why was it redrawn?
- Owner (2026-11-20): the row scrolled at least four dialog heights; AT2 (#10860) had moved only the guide. The architect placed every part (#10869); AT2b (#10898) built it.

## What does Claude's opened row hold? (AT2b)
- One column (`ws_claude_row_html()`, values read by `ws_claude_settings_card()`): notices one line each, only when true (not HTTPS; an old prompt, with "Show the new prompt" opening the set-up drawer; the last error; "Requests wait until …"); the switch; the routine's address; its token with "Stored encrypted and never shown again." and "Remove the stored token" on one line; Daily summary (Hour, Channel).
- Then one row of quiet small buttons: Try the connection (ready only), Set up on claude.ai / Finish the set-up (`ws_claude_setup_button()`), The latest requests; at the right the grey line "Last request 14:05 · Claude answered".
- Last the fold "Application and key" (`.pg-gather-toggle`, `ws_claude_fold_script()`): the application's name with Application Access, Key with Copy, the secret's last four, Reset secret, "Use another application" with its select and the permissions. Shut while a suitable application is chosen; open with none (then Create Claude's key), one lacking a needed permission, or a new secret.
- No help line in the row is over 110 characters. Test: `tests/settings_claude_row_test.php` pins the counts outside the fold (one switch, two text fields, one checkbox, two selects, three buttons) and the posted names.

## What do Claude's drawers hold?
- "Set up on claude.ai" (`#pgset-ws-claude-setup`, `ws_settings_claude_setup_drawer()`): "Still missing" (`ws_claude_missing()`) at the top, the steps of `ws_claude_setup_guide()` as `.stepper.pg-stepper-cards` (`ui/step-rail.md`), the prompt only in its step, then "How does this work?" with every sentence the row says in one line (`ws_claude_how_it_works()`). Test: `tests/settings_claude_setup_drawer_test.php`.
- "The latest requests" (`#pgset-ws-claude-requests`, `ws_settings_claude_requests_drawer()`): the version of the instructions with its help, then the ten newest requests (`ws_claude_latest_requests()`, read once a page).

Related: docs/memory/ui/settings-fold-rows.md, docs/memory/ui/settings-ai-voice-rows.md
