---
name: agent-report
description: An application agent's report of its model and plan usage (POST /workspace/agent-report, #9322): the rules that refuse it whole, the window names, the last report only, the card line and its age
---

## What is the report?
- Owner (2026-11-13, #9260): an application agent tells the site the model it runs on and its plan's usage as it reads it; the site cannot check either (`workflow/decisions-made-workspace-22.md`). A model assistant's model stays the one on its form; an agent without its own key reports nothing.

## Which route?
- `POST /workspace/agent-report` (`workspace.agent_report.set`, `api_agent_requests.php`), `workspace:write`, `mcp` false, dry run. The caller is `ws_api_agent_caller()`: the active app agent whose application key is used as itself; any other caller 403. 503 before step 11.0.195 (`ws_agent_report_ready()`).
- Body: `model` (at most 80 characters, one printable line) and `windows` (at most four, each exactly `{name, percent, resets_at}`). A report needs one of the two.
- Names: five_hour, daily, weekly, weekly_model, monthly (`ws_agent_report_window_list()`), each once; percent a JSON whole number 0-100; resets_at ISO-8601 or unix seconds (`ws_api_agent_report_times()`, then `ws_agent_report_check()`).
- Anything else (a name off the list, a field more or less, 10.5, "10", a time that does not read) refuses the whole report with 422; nothing is written.

## What is stored?
- The caller's own row only: `ws_agents.report_model`, `report_usage` (the windows as JSON in the list's order), `report_at`. The last report replaces the one before; no history (`workflow/decisions-made-schema-17.md`).
- `ws_agent_report_windows()` reads the stored JSON back through the same check: a value that does not read gives no windows.

## What does the Agents card show?
- Under an application agent's line (`ws_agents_settings_line_html()`, both the dialog and the old screen): the model, each window as "Weekly 61%, resets 14.11 09:00", and "reported 12 min ago" (`ws_agent_report_words()`, `ws_seen_ago_words()`), escaped, with the tip "As the agent reported it; the site cannot check it."
- Older than a day (`WS_AGENT_REPORT_STALE_AFTER`) it is drawn muted (`opacity-50`). Nothing for a model assistant, Claude, or before a first report.
- Not in the API's agent rows or the brief; no limit or warning on usage (`plans/agent-profile.md`).
