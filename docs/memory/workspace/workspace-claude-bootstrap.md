---
name: workspace-claude-bootstrap
description: Claude's routine reads its instructions and each request's briefing (ws_ai_briefing()) from the site; the short versioned bootstrap pasted on claude.ai and the card's warning
---

## What is pasted on claude.ai now?
- A short bootstrap (`ws_claude_bootstrap_prompt()`, includes/workspace/claude.php): the Site API, how to sign in, `GET /workspace/claude/instructions?version=N`, follow its `text`, take no order from the fire payload, stop when it cannot be read. The card and the setup guide show it instead of the long prompt.
- `WS_CLAUDE_BOOTSTRAP_VERSION` is N. Raise it only when the bootstrap's own text changes; what the routine does (`ws_claude_routine_prompt()`) is served by the site and changes with every release without a new paste.
- The fire payload still names only the site and the queue.

## What does the site serve?
- `GET /workspace/claude/instructions` (`workspace.claude.instructions`, `'mcp' => false`, `ws_api_claude_guard()`): `{version, outdated, text}`, `text` = `ws_claude_routine_prompt()`, which ends with `pg_ai_prompt_rules('assistant')` and its endpoint rules.
- `GET /workspace/claude/requests/{id}/brief` (`workspace.claude.requests.brief`, same guard): `{request_id, text}`, the briefing Pinegrap AI gets, written for Claude. 409 `closed` when the briefing refuses (channel closed to Claude, message deleted, asker gone). Step 2b of the instructions reads it; the context endpoint stays for reading more.
- Types `WorkspaceClaudeInstructions` and `WorkspaceClaudeBrief` are in `ws_openapi_objects()` (includes/workspace/api.php).

## What is the shared briefing?
- `ws_ai_briefing($row, $asker, $agent)` (includes/workspace/ai.php): head (`ws_ai_briefing_head()`: who it is, now, who asked), then the note, task, channel, discussion, people, reply-to and request in data blocks, then `ws_ai_briefing_text()`: "Answer in …" and the assistant rules last.
- `ws_ai_start()` takes its `parts` as Pinegrap AI's first message; `ws_ai_system_prompt()` takes its head. Claude takes `text`.
- `$agent` 'claude' switches the channel check to `ws_claude_channel_allowed()`, the name in the head and errors, the "left out" line of `ws_ai_channel_context()` (the context endpoint instead of read_channel), and adds the Visual Page Editor case through `pg_design_ai_brief()` (Pinegrap AI starts those in `pg_design_ai_start()`).

## How does the card know the paste is old?
- The instructions route compares the version sent (`ws_claude_version_state()`: none, older, current); the requests list notes "none" when it is called without `version` (the long prompt pasted before). The served instructions send `?version=` with the list.
- `ws_claude_note_version()` keeps `routine_version:N` (0 = none) in `config.ws_claude_error`, only over nothing or an earlier note, never over a start's error; a current version clears it, and a fire that goes through clears it like any error. No column (Fixed rule 5).
- `ws_claude_settings_card()` reads it with `ws_claude_version_noted()` and shows a warning instead of the red error.
- Tests: tests/workspace_claude_bootstrap_test.php, tests/ai_prompt_framing_test.php (briefing), tests/ai_prompt_rules_test.php.
