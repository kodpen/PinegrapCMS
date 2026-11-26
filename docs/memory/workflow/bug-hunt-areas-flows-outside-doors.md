---
name: bug-hunt-areas-flows-outside-doors
description: The bug hunt in Workspace flows reached from outside and by another hand (web address start, shares, a role-3 member's flows; architect 2026-11-26, request 3045): fixed, read clean, left
---

## Which area was it?
- Flows reached from outside and by another hand: `flow_start.php`, `includes/workspace/flow_web_start.php`, `flow_shares.php`, `flow_member.php`, and the run, save and publish checks they lean on in `flows.php`, `flow_runs.php`, `flow_versions.php`, `steps.php`.
- Memory read first: `workspace/workspace-flows-web-start.md`, `workspace-flows-shares.md`, `workspace-flows-member.md`.

## What was fixed?
- Another flow's "Start a flow" box started a role-3 member's flow (one that kept a home channel or a share from its owner's staff days), past "only its owner runs it" and the 20 starts an hour: `ws_flow_chain_check()` and `ws_flow_chain_start()` now ask `ws_flow_member_starter_refusal()` and `ws_flow_member_starts_check()` as a start by hand does.
- A colleague shared at edit before the owner became role 3 saved boxes the owner's flow may not hold (webhook, e-mail …); a staff member publishing a converted flow was held to their own kinds: save and publish now ask `ws_flow_owner_builder_kind()` (the owner as they stand now).

## What became an issue or a question?
- Issue: the firewall log (`waf_log.request_url`) keeps the full address with its query, so a flow's secret (`flow_start.php?key=`) is stored there when a call is refused by the rate limit or a rule, readable on the Firewall log screen. The text log already drops the query.
- Asked: a person shared at edit sees the address and its secret; when the share is removed or they leave, the address keeps working until the owner presses Renew. Renewing on removal is a product choice.

## What was read and found clean?
- Web address: only a live flow whose live version starts with `web`; paused, binned and never-published refused (binned as 401 like a wrong key); secret only in `config_text`, never in the graph, versions, templates, copies or API reads; resealed with the site key.
- Web address checks: `hash_equals()` after a shape check; Renew writes over the row, so the old key stops at once; 60 calls a minute counted before the key; per-flow open and hourly caps; a list or object field reaches `{{trigger.*}}` as ''; staff owner only.
- Shares: a view share starts no run (403) and reads the live graph, not the draft; site members and people out of the team reach nothing; channel shares re-read membership; publish, delete, resume, share and moving the home are the owner's; box try is the owner's.
- Role-3: every create path (panel, API, template, quick setup, AI draft, version restore) saves through `ws_flow_save()`; kinds re-checked at run and box try; anchor starts re-check the event right and the hourly cap; change boxes propose by the owner's kind at run.

## What is left?
- `ws_flow_publish_check()` checks a converted flow's boxes with the publisher's rights, while the run acts as its owner.
