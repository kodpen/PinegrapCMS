---
name: channel-activity-steps
description: Plan channel-activity, steps KA1 to KA5: the activity read with its API route, the Activity tab's script, the report's context, project and branch, the repository numbers, close-out
---

## Which steps are there?
- KA1 (M) first: the read. Done (#10751). Readers KA3 and KA4 add, called by the read once they exist: `ws_agent_report_context()`, `ws_agent_report_project()`, `ws_agent_report_branch()` (each takes the agent row) and `ws_channel_activity_repo($viewer, $channel, $now)`.
  - New `includes/workspace/channel_activity.php`: `ws_channel_activity($viewer, $channel, $now)` with pure parts (summary, row order, a request's age, plan windows joined by agents). The panel action `ws_channel_activity` beside `ws_agent_card` in `channel_agents_panel.php`; the route in `api_channel_reads.php` with its schema object; `php tools/check_api_schema.php`.
  - The answer has its whole shape from the start: `context`, `project`, `branch` and `repository` are null until KA3 and KA4 land (their readers are called when the function is there).
  - Tests, red first, in a new `tests/workspace_channel_activity_test.php`. Features: E2 to E7, E9, the read of E13.
- KA2 (M) after KA1: the tab.
  - New `assets/js/workspace_channel_activity.js`, in the bundle list beside `workspace_channel_agents.js` (`pg_js_bundles()`); in `workspace.js` only the tab row and the hook in `drawInfo()`; style in `backend.src.css` by look A's recipes; texts with `php tools/tr_add.php`.
  - It draws every part of the answer and leaves a null part out. Tests on the pure parts: when the tabs show, the remembered tab, a row's words, the 15 second brake.
  - Seen on dev in the agent channel, a wide screen and a phone; the PR says what was seen. Features: E1, E3 to E9, the screen's part of E13.
- KA3 (S) after KA1, beside KA2: the report's fields. `includes/workspace/agent_report.php`, `api_agent_requests.php` (the report and the answer route), `tools/ws_profile.php`, the instructions' line, `ws_agent_card()` (the card shows context). Tests: each refusal, the stored list still read, the object's round trip and its size. Features: E10 to E12.
- KA4 (S) after KA1, beside KA2 and KA3: the repository numbers. `includes/fn/github_links.php` (pure: a written line read back into kind, state, number, branch, address and title; a day's counts) and a new `includes/workspace/channel_activity_repo.php`. Tests in `tests/github_links_test.php`: every line shape the receiver writes is read back. Feature: E8.
- KA5 (S) close-out, after all: every E number checked against the code with its PR; `workspace/channel-activity-tab.md` written; `workspace/agent-report.md`, `agent-card-facts.md`, `workspace-channel-panels.md` and `workflow/agent-channel-wake.md` (when a session reports its context) corrected; the user documentation's page noted in `workflow/user-docs-pages.md`; this plan deleted.

## Which steps run side by side?
- After KA1: KA2, KA3 and KA4, each in its own files. `workspace.js` and `backend.src.css` are entered by KA2 alone; the coordinator gives it out away from an open PR that holds them.
- `plans/channel-documents.md` enters `workspace.js` too (KD2): KA2 and KD2 never at once.

## What does each step's PR carry?
- `Decision: channel message 10572`; no migration step; what was seen on dev where a step draws.
