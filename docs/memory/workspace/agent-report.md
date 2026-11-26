---
name: agent-report
description: An agent's report of model, plan usage, context, project and branch (POST /workspace/agent-report, #9322, #10810): what refuses it, the stored object, the card line and its age
---

## What is the report?
- Owner (2026-11-13, #9260): an application agent tells the site the model it runs on and its plan's usage as it reads it; the site cannot check either (`workflow/decisions-made-workspace-22.md`). A model assistant's model stays the one on its form; an agent without its own key reports nothing.

## Which route?
- `POST /workspace/agent-report` (`workspace.agent_report.set`, `api_agent_requests.php`), `workspace:write`, `mcp` false, dry run. The caller is `ws_api_agent_caller()`: the active app agent whose application key is used as itself; any other caller 403. 503 before step 11.0.195 (`ws_agent_report_ready()`).
- Body: `model` (at most 80 characters, one printable line), `windows` (at most four, each exactly `{name, percent, resets_at}`), `context` (`{used}`, whole tokens up to 10,000,000; `{used, size}` is still taken, used at most size, size 1 to 10,000,000), `project` (at most 80, one printable line), `branch` (at most 120, a git ref's characters). A report needs one field (#10810).
- Names: five_hour, daily, weekly, weekly_model, monthly (`ws_agent_report_window_list()`), each once; percent a JSON whole number 0-100; resets_at ISO-8601 or unix seconds (`ws_api_agent_report_times()`, then `ws_agent_report_check()`).
- Anything else (a name off the list, a field more or less, 10.5, "10", a time that does not read) refuses the whole report with 422; nothing is written.

- The model and the context with an answer, and the brief's hourly ask: `agent-report-hourly.md`.

## What is stored?
- The caller's own row only: `ws_agents.report_model`, `report_usage`, `report_at`. The last report replaces the one before; no history (`workflow/decisions-made-schema-17.md`).
- `report_usage` (VARCHAR 1000) holds one object `{windows, context, project, branch}`; a test keeps the largest under 1,000 bytes. A list stored before #10810 still reads as the windows (`ws_agent_report_read()`); a value that does not read gives nothing.
- Readers: `ws_agent_report_windows()`, `ws_agent_report_context()`, `ws_agent_report_project()`, `ws_agent_report_branch()`.

- The context's size and what is drawn without one: `agent-context-size.md`.

## What does the Agents card show?
- Under an application agent's line (`ws_agents_settings_line_html()`, both the dialog and the old screen): the model, each window as "Weekly 61%, resets 14.11 09:00", and "reported 12 min ago" (`ws_agent_report_words()`, `ws_seen_ago_words()`), escaped, with the tip "As the agent reported it; the site cannot check it."
- Older than a day (`WS_AGENT_REPORT_STALE_AFTER`) it is drawn muted (`opacity-50`). Nothing for a model assistant, Claude, or before a first report.
- In a channel: the agent card's bars and context, the 90% mark in the @ menu (`agent-card-look.md`, `agent-state-marks.md`), the API's channel-agent rows (`agent-card-facts.md`) and the Activity tab (`channel-activity-tab.md`). The brief only asks for a stale model.

## How does a session send it?
- `php tools/ws_profile.php --model=NAME --windows=NAME:PERCENT:RESETS[,…] --context=USED --project=NAME --branch=NAME` with the `PG_WS_*` settings; exit 0 reported, 1 refused, 2 usage. USED:SIZE is still sent for a harness that reads its size. The instructions and the brief say: send the tokens you use, never guess the window's size (`agent-context-size.md`). When: `workflow/agent-channel-wake.md`.

## What was left out?
- Owner and architect (2026-11-13, #9260): no history or chart of reports, no limit on plan usage, no rights by role, no mention of everyone holding a role.
- Owner (2026-11-15, channel decision 3775): a window at 90% or more is now marked on the agent card and in the @ menu (`agent-state-marks.md`); the mark stops nothing.
- Not tried: whether a cloud session can read its plan's limits; agents on one account report the same account's numbers.
