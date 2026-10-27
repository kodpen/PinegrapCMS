---
name: session-token-binding
description: How a signed-in session is bound to its auth_tokens row (selector kept in the session), what ends a session server-side, and the rules every sign-in and minting path must follow.
---

## What is the binding?
- `pg_login_set_device_cookie()` mints the `software[auth]` token and calls `pg_session_bind_token($selector, $user_id)`: the selector and the account it was minted for are kept in `$_SESSION['software']['auth_selector']` / `auth_user_id`. The remember-me paths (`initialize_user()` in `includes/fn/auth.php` and in `get_file.php`) bind the selector they just verified.
- Helpers live in `includes/authentication.php` because `get_file.php` shares them; tests in `tests/session_token_binding_test.php`.

## What ends a session on the server?
- `pg_session_token_binding_broken()` runs first in the binding block of both `initialize_user()`s: the row for the session's selector must exist, be unexpired and carry the session's `user_id`, and a cookie that arrives must name the same selector. Otherwise `session_destroy()`, the cookie is cleared and the activity log says why (`session token was revoked` / `expired` / `belongs to another account` / `does not match the device`).
- So every path that deletes `auth_tokens` rows - a password change, `set_password.php`, "sign out everywhere", `view_sessions.php`, `edit_user.php`, account deletion, the device limit, turning the second factor on or off - ends the sessions behind those rows on their next request, even from a client that stopped sending the cookie. Before this, such a client kept its session and was minted a fresh token.
- A session that carries a binding is never minted a second token by the quiet bind (`auth_bind_attempted`); that bind is only for sessions from before universal binding.
- The activity stamps (`last_used_at`, every 50 s in `initialize_user()` and on a remember-me redeem) also write `ip_address` from `pg_auth_request_address()` (`waf_client_ip()` when the firewall is loaded), so a row's address is where the device is now, not where it signed in: the session lists show it, and the firewall asks it before an automatic ban (`waf/waf-offence-fold-and-staff-address.md`).
- Upgrade bridge: the lookup is a bare `mysqli_query`; without the `auth_tokens` table it fails quietly and the session is left alone.

## What must a new sign-in path do?
- `pg_session_sign_in()` drops a binding made for another account (so `login_as_user.php` does not inherit the administrator's row) and keeps one made for the same account; mint with `pg_login_set_device_cookie()` before or after it, both orders work.
- Never write `sessionuserid` without going through `pg_session_sign_in()`, and never mint a token outside `pg_login_set_device_cookie()`: a session with a user but no binding is accepted, but then a revoke cannot end it.

## Was it checked on a running site?
- Sandbox (`tools/setup_sandbox.sh`), 2026-10-19, a basic user signed in on several cookie jars: deleting one device's `auth_tokens` row, as every revoke path does, signed that session out on its next request (panel and a private file through `get_file.php`, log "session token was revoked") while the other devices stayed in; a client that had stopped sending `software[auth]` was signed out the same way and no fresh token was minted; with the table renamed away (an install mid-upgrade) every session kept working without errors.
