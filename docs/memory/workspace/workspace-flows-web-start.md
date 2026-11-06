---
name: workspace-flows-web-start
description: The web address start box (kind web, B7): flow_start.php?flow=&key=, a sealed secret per flow in config_text, editors only, 60 calls a minute per address, 64 KB, declared body fields as trigger.*
---

## What starts the flow?
- A POST to `flow_start.php?flow=<id>&key=<secret>` (the key may come as the `X-Flow-Key` header): its own root file, no session, outside the API's keys. `ws_flow_web_receive()` answers JSON: 405 not POST, 429 over the allowance or the flow's own limits, 503 not ready, 401 no flow, no address or a wrong key (all alike; a firewall offence), 413 over 64 KB, 400 unreadable JSON, 409 not live, not started by this box or its owner left, 201 `{ok, run_id}`.
- Only a live flow whose live version's start box is `web`; the run is in its owner's name (started_by and actor the owner, start key `web:<random>`), under `ws_flow_start_refusal()` (open runs, starts an hour), staff owners only: the kind is not in `ws_flow_member_kinds()`.

## Where is the secret?
- 32 random bytes as 64 hex characters, sealed with the site's key (`encrypt_string_with_iv()`, "cipher:iv") in `config_text` row `ws_flow_web_<flow id>`: no schema change, never in the graph, so versions, templates, copies and API reads never carry it. In `pg_reseal_registry()` (kind iv, key name). No encryption key: no address.
- Compared with `hash_equals()` after a shape check (`ws_flow_web_secret_matches()`).
- Shown and renewed only through the panel action `ws_flow_web_address` (`ws_flow_for(..., 'edit')`, `renew: 1` makes a new one, the old stops at once; activity log line). The canvas box draws it with a Make / Renew button (`webAddressField()`).

## What does a run read?
- The box declares `fields` (key `^[a-z_][a-z0-9_]{0,31}$`, type text or number, at most 20: `ws_flow_web_normalize()`); `ws_flow_trigger_outputs('web', $config)` gives them as `{{trigger.<key>}}`.
- `ws_flow_web_body()`: a JSON object's top-level values (a body starting with `{` is JSON whatever its type), else `$_POST`; a JSON list or bad JSON is 400. `ws_flow_web_values()` keeps the declared fields only: number a finite number as sent, else ''; text cut to 2,000 characters, not UTF-8 ''; a list or object ''. Kept in the run context as `web`; `ws_step_trigger_values()` reads it back (`ws_flow_web_context_values()`), no record right is asked.

## What limits it?
- 60 calls a minute per address subject (`waf_rate_exceeded(waf_ip_subject($ip), 'flow_web', 60, 60)`, only `waf_ip_is_counted()` addresses), counted before the key is read; a refusal is logged (`rate-flow-web`) and registered with the burst flag. `flow_start.php` is in `waf_is_api_request()` and `waf_country_api_scripts()` like the other webhooks.

## Where else is it named?
- `ws_flow_start_names()` web → web (API `start` filter value `web`), the start sentence "When a web address is called", the AI catalogue hint, canvas KINDS/PALETTE and the globe icon. Tests `tests/workspace_flow_web_start_test.php`.
