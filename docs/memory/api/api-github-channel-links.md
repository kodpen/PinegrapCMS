---
name: api-github-channel-links
description: GitHub repository links of a Workspace channel (G2): link tables, the settings card, hook creation and removal, github_webhook.php, the GitHub application, the [ci]/[pr]/[label] lines
module: github_links
---

## Where are links kept, and how is one saved?
- `ws_channel_links` / `ws_channel_link_deliveries`, step `upgrade_2026_11_0_ws_channel_links()` (11.0.175, #8271; decision #8270 in `workflow/decisions-made-schema-13.md`). Code: `includes/workspace/github_links.php` (DB, receiver, card); pure rules in `includes/fn/github_links.php`, tested in `tests/github_links_test.php`.
- Workspace settings card "GitHub repositories" (`#ws-github`): only a manager or above holding the Workspace settings right (`ws_github_links_can_manage()`); channels = public/private ones the person may post in; a private channel's link is hidden from non-members.
- Save: row first (its id is in the hook address `github_webhook.php?link=<id>`), then `POST /repos/{o}/{r}/hooks` through the `channel_links` account; anything but 201 deletes the row and the card shows `pg_github_api_problem()`. Secret: 32 random bytes hex, sealed with `pg_connect_seal()`; no encryption key, no link.
- Remove (card, or ending the GitHub account in `pg_connect_disconnect()`, which runs before the token is revoked): `DELETE` the hook best effort, then the row and its delivery ids.

## Who writes the lines?
- The "GitHub" application: an `api_apps` row with no scope and no secret, made on the first save for the saver; its id is the service entry `github_channel_app` (`pg_connect_service_put()`), made again if the app was deleted. Lines go through `ws_message_send()` with that app id, acting for the link's maker (`created_by`); if the maker can no longer post there, the delivery fails and the card says so.

## How does github_webhook.php answer?
- 405 not POST; 429 over 120/min (WAF bucket `github_webhook`); 503 tables missing or Workspace off; 413 over 1 MB; 401 unknown link or wrong `X-Hub-Signature-256` (same answer, firewall offence); 400 not JSON or no delivery id; 200 `pong` (clears the card's error), `ignored`, `duplicate`, `written`; 500 `not_written` with the reason on the card and the delivery id given back so Redeliver works.
- Idempotent: `INSERT IGNORE` of (link, delivery id) before writing; delivery ids older than 7 days are pruned on 1 in 50 writes. Listed in `waf_is_api_request()` and `waf_country_api_scripts()`.
- Not run against GitHub or a database in the session that built it (no DB in the container); the unit tests cover the pure rules only.

## Which lines are written?
- `workflow_run` completed: `[ci] green|red #<n> <branch> <sha7> <workflow>=<conclusion> <run url> <title>`. Today's CI line wrote one `job=result` per job; a hook knows only the workflow's conclusion. `#<n>` is left out for a run of no pull request (a push to a branch). `success`/`neutral` are green; `failure`, `timed_out`, `action_required`, `startup_failure`, `stale` red; `cancelled`/`skipped` write nothing.
- `pull_request` labeled with a label the link follows: `[<label>] #<n> <branch> <sha7> <title>`, so `ready` gives today's `[ready]` line. Label names are folded to `a-z0-9._-`, at most 40.
- `pull_request` opened, reopened, closed: `[pr] opened|reopened|merged|closed #<n> <branch> <sha7> <url> <title>` (new prefix). Pushes (`synchronize`) and `ping` write nothing.
- Free text from a payload is one line, control characters folded, the title at most 200 characters.
