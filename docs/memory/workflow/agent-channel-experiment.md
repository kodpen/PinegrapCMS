---
name: agent-channel-experiment
description: Which doors wake a cloud session (A0, 2026-11-07): session webhook refused unsigned, routine /fire with an owner token, ws_wait.php poll; CI [ci]/[ready] lines; A3 door fields
---

## Does a plain POST to a session's inbound webhook wake it?
- No. `watch_url` gives `https://api.anthropic.com/integrations/v1/code/webhook-triggers/<id>/fire` and a `sealed_secret` (`fsw1:…`). An unsigned POST answers `401 unauthorized`, a GET `404`. The secret is sealed to Anthropic's artifact service: the session cannot read it, so no site can sign a delivery. The URL also dies with the session (a resumed session needs a new one). Not a door for a site.

## Does a routine's /fire wake a session?
- Yes, with a token the owner makes. `POST https://api.anthropic.com/v1/claude_code/routines/<trig_…>/fire`, `Authorization: Bearer <token>`, `anthropic-beta: experimental-cc-routine-2026-04-01`, `anthropic-version: 2023-06-01`, body `{"text": …}`, as `ws_claude_fire()` sends it. Without a token: `401 authentication_error`.
- A routine is either fresh-session-per-fire (today's Claude routine: 1-3 minutes to start) or bound to one session (`persistent_session_id`): its fire delivers the stored prompt and the text into that session as a new turn.
- A session can make a routine bound to itself (`create_trigger` with no schedule) and wake itself with `fire_trigger`, but such a routine has no API token (`api_token_hint` empty): only the owner generates one on claude.ai (routine, Trigger, API). So a session-bound push door needs the owner once per session.
- `fire_trigger` from the session on a self-bound routine: the run succeeded within a second and its prompt reached the session as a queued notification while a turn was running, read when that turn ended (about 7 minutes later): a wake never interrupts a turn.

## Does the poll door work?
- `tools/ws_wait.php` waits on `GET /workspace/sync?channel_id=` with the cursor and `If-None-Match`, ignores the newest 50 of the first answer, prints the first message matching `--prefix`/`--contains`/`--regex` as JSON (exit 0; 3 timeout). Run with Bash `run_in_background` (up to 2 h): the session wakes when it exits. Checked against a local stand-in of the two routes (304 path included); not against a real site.
- `tools/ws_post.php` writes a line (`POST /workspace/channels/{id}/messages`, `Idempotency-Key` optional). Both read `PG_WS_SITE` (ending `/integration.php`), `PG_WS_KEY`, `PG_WS_SECRET`, `PG_WS_CHANNEL`; the key goes as Basic auth, https only (localhost excepted).
- Not reached from the cloud: dev.pinegrap.com is denied by the environment's network policy (proxy CONNECT 403). The owner adds it under the environment's Allowed domains; the agents' application keys do not exist yet.

## What does CI write?
- `php-checks.yml` job `channel` (after checks, guards, php71, a non-draft PR to development): `[ci] green|red #<n> <branch> <sha7> checks=… guards=… php71=… <run url> <title>`. `agent-channel.yml` on the `ready` label: `[ready] #<n> <branch> <sha7> <title>`. Both use the CI application's own key, are skipped while a secret is missing, never fail the run, and are idempotent per run / per head.
- Repository secrets the owner sets: `PG_WS_SITE`, `PG_WS_CHANNEL`, `PG_WS_CI_KEY`, `PG_WS_CI_SECRET`. A fork's PR gets no secrets and is skipped.

## What does this fix for A3's door fields?
- Door kind `push`: a routine `/fire` address with its sealed token, or any webhook receiver that checks the site's own signature; the body carries the site address and ids only. A session webhook (`webhook-triggers`) is refused: nothing outside Anthropic can sign it. Which URLs A3 accepts is A3's call within the plan.
- Door kind `poll`: no URL or secret; the card shows the last read (`last knock`) of `GET /workspace/agent-requests` or `/workspace/sync`.
- A failed push stays queued for the next poll or claim, as the plan says; a session-bound routine dies with its session, so a coordinator's door is re-entered when its session changes.
