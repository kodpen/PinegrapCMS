---
name: workspace-stale-token-renewal
description: A Workspace request refused for a stale page token gets the session's current token (api.php session_token) and is sent once more; an ended sign-in keeps the typed message in the box (#11318)
---

## Why did a page left open overnight answer "Your session expired"?
- The server drops an idle PHP session; the next request is signed in again by the remember-me cookie, and `pg_session_sign_in()` renews the form token. The page still sends the token it was drawn with, so `validate_token()` refuses although the person is signed in. Reloading fixed it (#11318, seen on a phone).

## What happens now?
- `validate_token()` in `api.php` answers that refusal with `code: 'token'`, apart from an ended sign-in (`code: 'session'`, `panel_login_refusal()`).
- `api()` in `assets/js/workspace.js` sends every Workspace action through `window.PGWsTokenRetry` (`assets/js/workspace_token_retry.js`): on `code: 'token'` it asks `session_token` for the session's token, puts it in `CFG.token` and sends the request once more. Requests refused together share one renewal. A second token refusal, or a renewal without a token, is shown as the first refusal.
- So sending, reactions, tasks and notes all renew silently: they all go through `api()`.
- Not covered: `apiUpload()` (a file post) and the `ws_calc` reads of `workspace_editor.js`, which hold their own copy of the config; they work again once any `api()` call has renewed the token in `CFG`, or after a reload.

## What is the session_token action?
- A row in `pg_panel_actions()` (`includes/panel/dashboard.php`, `pg_panel_session_token()`): `any_role`, so the general gate refuses a request without a signed-in session with `code: 'session'`; it reads, needs no token, and refuses a request that signed in with a password (no page token to renew). It answers `data.token`.
- Safe to hand out: api.php never sends `Access-Control-Allow-Credentials`, so another site cannot read a credentialed answer; the same token is printed on every page the person opens.

## What does the person see when the sign-in really ended?
- The composer's `sendFailed()` (send, edit, scheduled edit): the box is emptied only after the server took the message, so the text stays; the draft is written at once, and the notice says "You are signed out, so the message was not sent. What you wrote stays in the box: sign in again, then send it."
- Tests: `tests/workspace_token_retry_test.php` (the rule in Node, the server side in source).
