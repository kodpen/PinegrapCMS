---
name: settings-ai-voice-rows
description: Pinegrap AI's and Voice's opened rows in Site Settings › Assistants and agents (AT5): one column, the fold Application, their drawers, what a test pins
---

## Why were they redrawn?
- AT4 (#10918) found both in two columns with paragraphs of help. The architect placed every part (`plans/assistants-settings-tidy-screens-2.md`, 2026-11-21); AT5 (#10948) built it as Claude's row was built (`settings-claude-row.md`).

## What does Pinegrap AI's opened row hold?
- One column (`ws_ai_row_html()`, values read by `ws_ai_settings_card()`, `includes/workspace/ai.php`): notices one line each, only when true (the licence's sentence when it is not entered, expired or refused; the last error; "Requests wait until …").
- The switch "Pinegrap AI can be asked in the channels with @ai" with the part's one line and "How does this work?" (`ws_assistants_help_button()`).
- The facts line (`.ws-ai-facts`, meta): Licence and its pill (a word: Valid until …, Not entered, Expired, Refused), Model with its code, the tokens line.
- One row of quiet small buttons: Try the connection (with a licence key), The latest requests; at the right "Last request 14:05 · …" or "Nobody has asked Pinegrap AI yet.".
- Last the fold "Application" (`.pg-gather-toggle`, the click is `ws_claude_fold_script()`'s `data-ws-claude-fold`): name, number, Application Access, "It needs no secret: Pinegrap AI runs inside this site.", then "Use another application" with the select, the required scopes and what it may read. Shut while a suitable application is chosen; open with none (Create Pinegrap AI's key and one line) or one lacking a permission.

## What does Voice's opened row hold?
- One column (`ws_voice_row_html()`, `includes/workspace/voice.php`): notices (the transcripts' and the speech's last error, "Recordings wait until …"); "Write voice messages out as text" with the part's line and "How does this work?"; Provider and Model side by side; OpenAI's connection block, or for Custom address the service's address and its key full width with "Stored encrypted and never shown again." and "Remove the stored key" on one line; the meta line "Recordings are sent to".
- Spoken answers: the switch with one line, the speech address and key only when it has its own (or the transcripts are not OpenAI), Model and Voice side by side, the meta line "Spoken answers are made by".
- The pill is `ws_assistant_state_pill()`, as Claude's and Pinegrap AI's. Look A fields only (`pg-input`, `pg-switch`, `pg-field-help`): the old `form-control` and `btn` markup went.

## Where did the rest go?
- "The latest requests" of Pinegrap AI: drawer `pgset-ws-ai-requests` (`ws_settings_ai_requests_drawer()`), the ten newest read once a page (`ws_ai_latest_requests()`).
- "Still missing": the row's head names the first (AT1); the whole list tops the part's guide drawer `pgset-ws-ai-guide` / `pgset-ws-voice-guide` (`ws_settings_assistants_guide_drawer($key, $in_dialog, $missing)`; Voice's is the transcripts' and the speech's lists).
- The sentences past one line: the guide steps (`ws_assistants_guide_steps()`): what is sent and the asker's rights under Switch it on, the test's minute under Try it, who pays under Choose the service, a step Spoken answers.

## What does a test pin?
- `tests/settings_ai_voice_rows_test.php`: no second column, no help line over 110 characters, no list of requests in the pane, the parts in order, the fold, the meta lines, the posted names (`ws_ai_enabled`, `ws_ai_app_id`, `ws_ai_test=1`, `ws_ai_key_create=1`; the twelve `ws_voice_*`), the drawers.

Related: docs/memory/ui/settings-claude-row.md, docs/memory/ui/settings-fold-rows.md, docs/memory/ui/settings-assistants-help-lines.md
