---
name: app-agent-own-application
description: Each application agent needs its own application: the Agents card refuses one another app agent works through, as its key resolves to one agent (#9741)
---

## Why may two app agents not share an application?
- Ground: `workflow/decisions-made-workspace-7.md` (owner, 2026-11-06), "A key per agent: each registry row acts with its own application or its own sealed model key, never a shared one". Architect (2026-11-15): the card refuses the second row; letting requests resolve to the calling agent of a shared key would build what the decision rules out.
- Every call made with an application's key is answered as one agent: `ws_api_agent_caller_row()` (`api_agent_requests.php`) and `ws_agent_wake_target()` (`agent_wake.php`, `<@app:N>`) take the first active `app` row of that `app_id` in id order. A second agent on the same application never lists, claims or answers its own requests; they are cancelled after a day, its approval answers count as the first agent's, and `/wake` sets the first agent's schedule (#9741).

## What refuses it?
- `ws_agent_save_check()` (pure, sixth argument `$apps_taken`) refuses an `app` agent whose application is in the list, field `app_id`: "Another agent already works through this application: give this agent its own application under Settings › API › Application Access." A `model` agent's application is not checked.
- `ws_agents_settings_check()` builds the list with `ws_agent_apps_taken($rows, $id)` (pure) from the `kind = 'app'` rows: every other app agent's application, whatever its status (an agent switched off still holds its application, so switching it back on cannot bring the fault back). No unique key on `ws_agents.app_id`; no schema change.

## What happens on a site that already has two on one application?
- Stored rows are not changed. Both keep running as before (calls go to the first in id order). The first agent, which the key resolves to, still saves: later agents on its own application do not count against it. Any later agent on that application is refused on its next save until it gets an application of its own.
- Tests: `test_workspace_agent_save_check`, `test_workspace_agent_apps_taken` in `tests/workspace_agents_test.php`.
