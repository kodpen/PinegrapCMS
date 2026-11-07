---
name: workspace-claude-instructions-version
description: Claude's claim refuses a run that has not read the site's current instructions (409 instructions_outdated): the hashed instructions version, the system line, the card and the guide warning
---

## What is the instructions version?
- `ws_claude_instructions_version($base)` (includes/workspace/claude.php): the first 8 hex digits of `sha1()` of `ws_claude_routine_text($base, '')`, the routine's instructions with the version left blank. It changes whenever the text does (an update, a new Site API), with no constant to raise; `WS_CLAUDE_BOOTSTRAP_VERSION` stays the version of the pasted bootstrap only (`workspace-claude-bootstrap.md`).
- `ws_claude_routine_prompt()` is `ws_claude_routine_text($base, <version>)`: step 2a says `POST …/claim with {"instructions": "<version>"}` and what a 409 `instructions_outdated` means (read the instructions again, start the queue again). Tests that read the prompt's source read `ws_claude_routine_text`.
- `GET /workspace/claude/instructions` also answers `instructions_version` (`WorkspaceClaudeInstructions`).

## What does the claim do with it?
- `POST /workspace/claude/requests/{id}/claim` takes body `instructions` (string, optional so a missing one is a 409, not a 422). `ws_api_claude_instructions_check()` runs after the closed/claimed checks and before `api_dry_run_stop()`; `ws_claude_instructions_state()` gives none, other or current (case and spaces ignored, `hash_equals`).
- other: a run that read an earlier text (an update landed mid-run): 409 only; it can put itself right.
- none: a run on a prompt that never reads the site (the long prompt, or one edited by hand): 409, `ws_claude_note_version(null)` so the card shows the long-prompt warning, and once per request a system line in its channel (`ws_message_system()`), marked by `ws_ai_requests.error = 'instructions_outdated'` with a conditional UPDATE so two runs write one line. No column (Fixed rule 5). A dry run writes nothing.
- The request stays queued for a run that has the instructions; the 24 h TTL cancels it otherwise.

## What do people see?
- The card's status column: "Version of the instructions the site gives the routine" with the version.
- The setup guide's routine step: a warning to paste the prompt as it is and add no instructions of one's own.
- Tests: tests/workspace_claude_bootstrap_test.php (version, state, claim order). No live routine run (A1 of `plans/agent-channel-steps.md`).
