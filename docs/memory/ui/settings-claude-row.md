---
name: settings-claude-row
description: Claude's opened row in Site Settings › Assistants and agents as drawn: the sentence, the grid, the line to Connections, Try and Set up, the fold Key and secret, the drawer Set up on claude.ai
---

## Why was it rebuilt to the drawing?
- AT2b (#10898) built it from the plan's words as one column; the drawing (boards A and B, `git show 95a7d40a:docs/memory/plans/assistants-settings-tidy/assistants.jpg`) has a grid and other words.
- Owner (2026-11-21, with a picture of the row on dev): "mimara özellikle çizdirttiğim ekrandaki düğme dizilimleri ve cümleleri dahil tamamen farklı olmuş", "mesela hazır etiketi yok, nasıl çalışır yok". A drawn screen follows its drawing (`workflow/screen-follows-drawing.md`).

## What does Claude's opened row hold?
- `ws_claude_row_html()`, values read by `ws_claude_settings_card()`. Notices one line each, only when true (not HTTPS; an old prompt, "Show the new prompt" opening the set-up drawer; the last error; "Requests wait until …").
- `.ws-claude-lead`: the switch, then "When @claude is written in a channel, your routine on claude.ai answers." and the link "How does this work?" (`data-ws-claude-how`), which opens the set-up drawer at `#ws-claude-how-title`.
- `.ws-claude-grid`, two columns (one under 576 px): Application (select; help "The record that holds who connects…" with the link Application Access) | Summary channel (channel select and the hour select side by side, help "Claude writes its daily summary to this channel.").
- Without the summary's upgrade the second cell is empty, so Application keeps its column.
- Under the grid `.ws-claude-routine`: "Routine address and token", Connected or Not connected, "Manage in Connections" / "Connect in Connections" (`ws_claude_connections_url()`). The fields moved there: `connections-claude-page.md`.
- `.ws-claude-acts`: Try (ready, administrator), Set up on claude.ai / Finish the set-up (`ws_claude_setup_button()`), the grey last line opening "The latest requests".
- Last the fold "Key and secret" (`.pg-gather-toggle`, `ws_claude_fold_script()`): Create Claude's key with no application; else Key with Copy, the secret's last four, Reset secret, the permissions. Open with no application, one lacking a permission, or a new secret.
- The head: "Ready · no run yet" or "Ready · last run 14:05" and the pill Ready / Not ready / Off (`ui/settings-fold-rows.md`).
- Test: `tests/settings_claude_row_test.php` (counts outside the fold, the grid's order, the sentence, the posted names).

## What does the drawer Set up on claude.ai hold?
- `ws_settings_claude_setup_drawer($in_dialog, $admin)`: Claude's tile, the title, the line "Five steps; every value has Copy beside it.", Close in the foot (`pg_settings_drawer()`'s `$foot`, `$line`, `$tile`).
- "Still missing" at the top, only while something is missing.
- Five steps (`ws_claude_setup_guide($host, $prompt, $key)`, `includes/workspace/claude_setup.php`), a title and one sentence each: Create the routine (the prompt in a code box with Copy), Set up its environment (Domain and, for an administrator, Key as value rows with Copy; "Where do the key and secret go?" folded), Add the API trigger, Paste the routine address and the token in Connections (with "Open Claude in Connections", a new tab), Try.
- Then the note "This connection answers as @claude in the channel…" and "How does this work?" (`ws_claude_how_it_works()`).
- The drawing's steps 1 and 2 add a custom connector and sign in; Claude connects through a routine and its API trigger, so the steps keep the real flow in the drawing's look.
- Test: `tests/settings_claude_setup_drawer_test.php`.

## What does "The latest requests" hold?
- `#pgset-ws-claude-requests`, `ws_settings_claude_requests_drawer()`: the version of the instructions with its help, then the ten newest requests (`ws_claude_latest_requests()`, read once a page).

Related: docs/memory/ui/settings-fold-rows.md, docs/memory/ui/settings-ai-voice-rows.md, docs/memory/workflow/screen-follows-drawing.md
